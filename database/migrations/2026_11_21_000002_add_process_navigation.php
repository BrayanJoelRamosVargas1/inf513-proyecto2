<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([['Administración', '/administracion', ['admin']], ['Adjudicaciones', '/adjudicaciones', ['student', 'authority']]] as $i => [$label,$path,$roles]) {
            $id = DB::table('navigation_items')->insertGetId(['label' => $label, 'path' => $path, 'position' => 10 + $i]);
            foreach ($roles as $role) {
                DB::table('navigation_roles')->insert(['navigation_item_id' => $id, 'role' => $role]);
            }
        }
    }

    public function down(): void
    {
        DB::table('navigation_items')->whereIn('path', ['/administracion', '/adjudicaciones'])->delete();
    }
};
