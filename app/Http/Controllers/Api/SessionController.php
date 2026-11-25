<?php

namespace App\Http\Controllers\Api;

use App\Domain\Selection;
use App\Domain\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class SessionController extends Controller
{
    public static function event(Request $r, string $event, int $status, ?string $resource = null): void
    {
        DB::table('access_events')->insert(['user_id' => $r->user()?->id, 'event' => $event, 'resource' => $resource, 'ip' => $r->ip(), 'status' => $status, 'created_at' => now()]);
    }

    public function csrf(Request $r)
    {
        return response()->json(['token' => $r->session()->token()]);
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($d + ['active' => true])) {
            self::event($r, 'login.failed', 422);

            return response()->json(['message' => 'Credenciales incorrectas o cuenta desactivada.', 'errors' => ['email' => ['No se pudo iniciar sesión.']]], 422);
        }
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
        return response()->json(['user' => $r->user()->only(['id', 'name', 'email', 'faculty', 'career', 'role']), 'token' => $r->session()->token(), 'roles' => Workspace::roles($r->user()), 'training_enabled' => config('portal.experience') === 'training']);
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
