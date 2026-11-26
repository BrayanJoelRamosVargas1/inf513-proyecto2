<?php

namespace App\Http\Controllers\Api;

use App\Domain\Selection;
use App\Domain\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

final class SessionController extends Controller
{
    public static function event(Request $r, string $event, int $status, ?string $resource = null): void
    {
        DB::table('access_events')->insert([
            'user_id' => $r->user()?->id,
            'event' => $event,
            'resource' => $resource,
            'ip' => $r->ip(),
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    public function csrf(Request $r)
    {
        return response()->json(['token' => $r->session()->token(), 'operational_mode' => Selection::operationalMode()]);
    }

    public function login(Request $r)
    {
        $r->merge(['email' => mb_strtolower(trim((string) $r->input('email')))]);
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        // B-25: además del límite por IP de la ruta, un límite por cuenta y origen frena la adivinación de claves.
        $attempts = 'login:'.mb_strtolower($d['email']).'|'.$r->ip();
        if (RateLimiter::tooManyAttempts($attempts, 5)) {
            self::event($r, 'login.locked', 429);

            return response()->json([
                'message' => 'Demasiados intentos fallidos. Espere unos minutos e intente de nuevo.',
                'errors' => ['email' => ['Demasiados intentos fallidos. Espere unos minutos.']],
            ], 429);
        }
        if (! Auth::attempt($d + ['active' => true])) {
            RateLimiter::hit($attempts, 300);
            self::event($r, 'login.failed', 422);

            return response()->json([
                'message' => 'Credenciales incorrectas o cuenta desactivada.',
                'errors' => ['email' => ['No se pudo iniciar sesión.']],
            ], 422);
        }
        RateLimiter::clear($attempts);
        $r->session()->regenerate();
        $r->session()->put('security_version', $r->user()->security_version);
        self::event($r, 'login.success', 200);
        Selection::audit($r->user(), null, 'session.login', 'user:'.$r->user()->id);

        return $this->me($r);
    }

    public function register(Request $r)
    {
        app(PortalController::class)->register($r);
        self::event($r, 'register.success', 201);

        return response()->json($this->me($r)->getData(true), 201);
    }

    public function me(Request $r)
    {
        return response()->json([
            'user' => $r->user()->only(['id', 'name', 'email', 'faculty', 'career', 'role']),
            'token' => $r->session()->token(),
            'roles' => Workspace::roles($r->user()),
            'training_enabled' => config('portal.experience') === 'training',
            'operational_mode' => Selection::operationalMode(),
            'must_change_password' => (bool) $r->user()->must_change_password,
        ]);
    }

    /** Cambio de clave por la propia persona: exige la clave actual y cierra las demás sesiones. */
    public function password(Request $r)
    {
        $d = $r->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:12|max:100|confirmed|different:current_password',
        ]);
        $user = $r->user();
        if (! Hash::check($d['current_password'], $user->password)) {
            self::event($r, 'password.rejected', 422);

            return response()->json([
                'message' => 'La clave actual no es correcta.',
                'errors' => ['current_password' => ['La clave actual no es correcta.']],
            ], 422);
        }
        DB::transaction(function () use ($user, $d) {
            $user->forceFill(['password' => $d['password'], 'must_change_password' => false, 'security_version' => $user->security_version + 1])->save();
            Selection::audit($user, null, 'user.password_changed', 'user:'.$user->id);
        });
        $r->session()->regenerate();
        $r->session()->put('security_version', $user->security_version);
        self::event($r, 'password.changed', 204);

        return response()->noContent();
    }

    public function logout(Request $r)
    {
        self::event($r, 'logout', 204);
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->noContent();
    }
}
