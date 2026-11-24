<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Navigation only: these entries grant no academic permissions.
        foreach ([['Revisión de expedientes', '/revision', 'reviewer'], ['Mis evaluaciones', '/evaluaciones', 'evaluator'], ['Seguimiento de expedientes', '/expedientes', 'authority']] as $i => [$label, $path, $role]) {
            DB::table('navigation_items')->insertOrIgnore(['label' => $label, 'path' => $path, 'position' => 3 + $i, 'active' => true]);
            $id = DB::table('navigation_items')->where('path', $path)->value('id');
            DB::table('navigation_roles')->insertOrIgnore(['navigation_item_id' => $id, 'role' => $role]);
        }
    }

    public function down(): void
    {
        DB::table('navigation_items')->whereIn('path', ['/revision', '/evaluaciones', '/expedientes'])->delete();
    }
};
