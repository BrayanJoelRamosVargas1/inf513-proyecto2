<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class Administration
{
    private function reason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 10) {
            Scores::fail('Describa el motivo administrativo (mínimo 10 caracteres).');
        }
    }

    public function profile(User $actor, int $id, array $data, int $version, string $reason): void
    {
        Access::transaction($actor, function () use ($actor, $id, $data, $version, $reason) {
            abort_unless($actor->role === 'admin', 403);
            $this->reason($reason);
            $target = Selection::row('users', $id, true);
            if ($target->profile_version !== $version) {
                Scores::fail('Perfil modificado por otra operación. Recargue la página.');
            }
            $allowed = array_intersect_key($data, array_flip(['name', 'email', 'role', 'active', 'faculty', 'career']));
            if (isset($allowed['role']) && ! in_array($allowed['role'], ['admin', 'authority', 'staff', 'student'])) {
                Scores::fail('Perfil inválido.');
            }
            if ($id === $actor->id && isset($allowed['role']) && $allowed['role'] !== $target->role) {
                Scores::fail('Otro administrador debe cambiar su perfil.');
            }
            if ($target->role === 'admin' && $target->active && (($allowed['role'] ?? $target->role) !== 'admin' || ! ($allowed['active'] ?? $target->active))
                && DB::table('users')->where('role', 'admin')->where('active', true)->count() <= 1) {
                Scores::fail('Debe conservar al menos un administrador activo.');
            }
            $allowed['profile_version'] = $version + 1;
            $allowed['security_version'] = $target->security_version + 1;
            $allowed['updated_at'] = now();
            DB::table('users')->where('id', $id)->update($allowed);
            $before = array_intersect_key((array) $target, array_flip(['name', 'email', 'role', 'active', 'faculty', 'career', 'profile_version']));
            Selection::audit($actor, null, 'user.profile_changed', 'user:'.$id, $before, array_diff_key($allowed, ['security_version' => 1]), $reason);
        });
    }

    public function grant(User $actor, int $call, int $user, string $role, ?string $start, ?string $end, string $reason, ?int $version = null): int
    {
        return Access::transaction($actor, function () use ($actor, $call, $user, $role, $start, $end, $reason, $version) {
            abort_unless($actor->role === 'admin' && $actor->id !== $user, 403);
            $this->reason($reason);
            Selection::row('calls', $call, true);
            Selection::row('users', $user, true);
            if (! in_array($role, ['reviewer', 'evaluator', 'authority'])) {
                Scores::fail('Permiso inválido.');
            }
            if ($start && $end && strtotime($start) >= strtotime($end)) {
                Scores::fail('El vencimiento debe ser posterior al inicio.');
            }
            $old = DB::table('memberships')->where(['call_id' => $call, 'user_id' => $user, 'role' => $role])->first();
            if ($old && $old->version !== $version) {
                Scores::fail('Permiso modificado: recargue antes de renovar.');
            }
            $d = ['starts_at' => $start, 'expires_at' => $end, 'revoked_at' => null, 'revoked_by' => null, 'reason' => $reason, 'version' => ($old->version ?? 0) + 1];
            if ($old) {
                $id = $old->id;
                DB::table('memberships')->where('id', $id)->update($d);
            } else {
                $id = DB::table('memberships')->insertGetId($d + ['call_id' => $call, 'user_id' => $user, 'role' => $role]);
            }
            DB::table('users')->where('id', $user)->increment('security_version');
            Selection::audit($actor, $call, 'permission.granted', 'membership:'.$id, $old, $d + ['user_id' => $user, 'role' => $role], $reason);

            return $id;
        });
    }

    public function revoke(User $actor, int $id, int $version, string $reason): void
    {
        Access::transaction($actor, function () use ($actor, $id, $version, $reason) {
            abort_unless($actor->role === 'admin', 403);
            $this->reason($reason);
            $m = Selection::row('memberships', $id, true);
            if ($m->version !== $version || $m->revoked_at) {
                Scores::fail('El permiso cambió o ya está revocado.');
            }
            DB::table('memberships')->where('id', $id)->update(['revoked_at' => now(), 'revoked_by' => $actor->id, 'reason' => $reason, 'version' => $version + 1]);
            DB::table('users')->where('id', $m->user_id)->increment('security_version');
            Selection::audit($actor, $m->call_id, 'permission.revoked', 'membership:'.$id, $m, ['version' => $version + 1, 'revoked' => true], $reason);
        });
    }

    public function editCategory(User $actor, int $id, array $data, string $reason): void
    {
        Access::transaction($actor, function () use ($actor, $id, $data, $reason) {
            abort_unless($actor->role === 'admin', 403);
            $this->reason($reason);
            $category = Selection::row('categories', $id, true);
            if (DB::table('offers')->where('category_id', $id)->exists()
                && ($category->name !== $data['name'] || $category->regime !== $data['regime'])) {
                Scores::fail('El tipo ya se usa en ofertas: conserve su nombre y régimen. Puede cambiar su disponibilidad para configuraciones futuras.');
            }
            $values = array_intersect_key($data, array_flip(['name', 'regime', 'active']));
            DB::table('categories')->where('id', $id)->update($values + ['updated_at' => now()]);
            Selection::audit($actor, null, 'category.updated', 'category:'.$id, $category, $values, $reason);
        });
    }

    public function reassign(User $actor, int $id, int $user, int $version, string $reason): void
    {
        Access::transaction($actor, function () use ($actor, $id, $user, $version, $reason) {
            $a = Selection::row('assignments', $id);
            $o = Selection::row('offers', $a->offer_id);
            $c = Selection::row('calls', $o->call_id, true);
            Access::require($actor, $c->id, ['authority']);
            $this->reason($reason);
            if ($c->mode !== 'DEMO' || ! in_array($c->status, ['draft', 'published', 'closed', 'evaluation'])) {
                Scores::fail('Reasignación cerrada.');
            }
            if ($a->version !== $version || $a->user_id === $user) {
                Scores::fail('Responsabilidad modificada o sin cambio.');
            }
            Access::require(User::findOrFail($user), $c->id, ['evaluator']);
            DB::table('assignments')->where('id', $id)->update(['user_id' => $user, 'version' => $version + 1, 'updated_at' => now()]);
            Selection::audit($actor, $c->id, 'assignment.reassigned', 'assignment:'.$id, $a, ['user_id' => $user, 'version' => $version + 1], $reason);
        });
    }
}
