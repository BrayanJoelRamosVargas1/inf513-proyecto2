<?php

namespace App\Http\Operations;

use App\Domain\Access;
use App\Domain\Administration;
use App\Domain\Scores;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cuentas, permisos por convocatoria y tipos de auxiliatura (solo administración).
 * Valida la petición y ejecuta la regla de dominio; no decide la respuesta HTTP.
 * Lo usan por igual los controladores Blade (redirección) y la API (JSON).
 */
final class AdministrationOperations
{
    public function saveUser(Request $r): void
    {
        abort_unless($r->user()->role === 'admin', 403);
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|unique:users|max:150', 'password' => 'required|string|min:12', 'role' => ['required', Rule::in(['student', 'staff', 'authority'])], 'faculty' => 'required|string|max:80', 'career' => 'required|string|max:80']);
        Access::transaction($r->user(), function () use ($r, $d) {
            abort_unless($r->user()->role === 'admin', 403);
            $u = new User;
            foreach ($d as $key => $v) {
                $u->$key = $v;
            }$u->save();
            Selection::audit($r->user(), null, 'user.created', 'user:'.$u->id, null, ['role' => $u->role]);
        });
    }

    public function membership(Request $r): void
    {
        abort_unless($r->user()->role === 'admin', 403);
        $d = $r->validate(['call_id' => 'required|integer|exists:calls,id', 'user_id' => 'required|integer|exists:users,id', 'role' => ['required', Rule::in(['reviewer', 'evaluator', 'authority'])]]);
        if ((int) $d['user_id'] === $r->user()->id) {
            Scores::fail('No puede elevar sus propios permisos. Otro administrador debe asignar el ámbito.');
        }
        $extra = $r->validate(['starts_at' => 'nullable|date', 'expires_at' => 'nullable|date', 'reason' => 'required|string|min:10|max:3000', 'version' => 'nullable|integer']);
        app(Administration::class)->grant($r->user(), (int) $d['call_id'], (int) $d['user_id'], $d['role'], $extra['starts_at'] ?? null, $extra['expires_at'] ?? null, $extra['reason'], isset($extra['version']) ? (int) $extra['version'] : null);
    }

    public function category(Request $r): void
    {
        abort_unless($r->user()->role === 'admin', 403);
        $d = $r->validate(['name' => 'required|string|max:100|unique:categories', 'regime' => ['required', Rule::in(['docencia', 'laboratorio'])], 'active' => 'nullable|boolean']);
        Access::transaction($r->user(), function () use ($r, $d) {
            abort_unless($r->user()->role === 'admin', 403);
            $id = DB::table('categories')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
            Selection::audit($r->user(), null, 'category.created', 'category:'.$id, null, $d);
        });
    }

    public function editCategory(Request $r, int $id): void
    {
        abort_unless($r->user()->role === 'admin', 403);
        $d = $r->validate(['name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($id)],
            'regime' => ['required', Rule::in(['docencia', 'laboratorio'])], 'active' => 'required|boolean',
            'reason' => 'required|string|min:10|max:3000']);
        $reason = $d['reason'];
        unset($d['reason']);
        app(Administration::class)->editCategory($r->user(), $id, $d, $reason);
    }

    public function profile(Request $r, int $id): void
    {
        $service = app(Administration::class);
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($id)], 'role' => ['required', Rule::in(['admin', 'authority', 'staff', 'student'])], 'faculty' => 'required|string|max:80', 'career' => 'required|string|max:80', 'active' => 'required|boolean', 'version' => 'required|integer', 'reason' => 'required|string|min:10|max:3000']);
        $service->profile($r->user(), $id, $d, (int) $d['version'], $d['reason']);
    }

    public function revoke(Request $r, int $id): void
    {
        $service = app(Administration::class);
        $d = $r->validate(['version' => 'required|integer', 'reason' => 'required|string|min:10|max:3000']);
        $service->revoke($r->user(), $id, (int) $d['version'], $d['reason']);
    }

    public function reassign(Request $r, int $id): void
    {
        $service = app(Administration::class);
        $d = $r->validate(['user_id' => 'required|integer|exists:users,id', 'version' => 'required|integer', 'reason' => 'required|string|min:10|max:3000']);
        $service->reassign($r->user(), $id, (int) $d['user_id'], (int) $d['version'], $d['reason']);
    }
}
