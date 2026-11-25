<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $t) {
            $t->id();
            $t->string('label', 100);
            $t->string('path', 100)->unique();
            $t->unsignedInteger('position');
            $t->boolean('active')->default(true);
        });
        Schema::create('navigation_roles', function (Blueprint $t) {
            $t->foreignId('navigation_item_id')->constrained('navigation_items')->cascadeOnDelete();
            $t->string('role', 30);
            $t->unique(['navigation_item_id', 'role']);
        });
        foreach ([['Inicio', '/', ['student', 'staff', 'authority', 'admin']], ['Convocatorias', '/convocatorias', ['student', 'reviewer', 'evaluator', 'authority', 'admin']], ['Mis postulaciones', '/postulaciones', ['student']], ['Estadísticas', '/estadisticas', ['student', 'reviewer', 'evaluator', 'authority', 'admin']], ['Pagos simulados', '/simulacion', ['student', 'staff', 'authority', 'admin']], ['Mi cuenta y ayuda', '/cuenta', ['student', 'staff', 'authority', 'admin']]] as $position => [$label, $path, $roles]) {
            $id = DB::table('navigation_items')->insertGetId(compact('label', 'path', 'position'));
            foreach ($roles as $role) {
                DB::table('navigation_roles')->insert(['navigation_item_id' => $id, 'role' => $role]);
            }
        }
        Schema::create('access_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->string('event', 40)->index();
            $t->string('resource', 160)->nullable();
            $t->ipAddress('ip');
            $t->unsignedSmallInteger('status');
            $t->timestampTz('created_at')->index();
        });
        Schema::create('page_visits', function (Blueprint $t) {
            $t->string('path', 160)->primary();
            $t->unsignedBigInteger('total')->default(0);
        });
        Schema::create('visit_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->uuid('key');
            $t->string('path', 160);
            $t->unique(['user_id', 'key']);
        });
        Schema::create('portal_preferences', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained('users');
            $t->string('theme', 20)->default('adultos');
            $t->string('mode', 10)->default('auto');
            $t->unsignedSmallInteger('font')->default(100);
            $t->boolean('contrast')->default(false);
        });
        Schema::create('sandbox_payment_methods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->string('label', 60);
            $t->string('token', 50)->unique();
            $t->boolean('active')->default(true);
            $t->timestampTz('created_at');
        });
        Schema::create('sandbox_payment_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->decimal('total', 10, 2);
            $t->string('description', 100);
            $t->uuid('key');
            $t->unique(['user_id', 'key']);
            $t->timestampTz('created_at');
        });
        Schema::create('sandbox_installments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained('sandbox_payment_plans');
            $t->unsignedSmallInteger('number');
            $t->decimal('amount', 10, 2);
            $t->date('due_at');
            $t->unique(['plan_id', 'number']);
        });
        Schema::create('sandbox_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('method_id')->constrained('sandbox_payment_methods');
            $t->foreignId('installment_id')->nullable()->constrained('sandbox_installments');
            $t->decimal('amount', 10, 2);
            $t->string('status', 20);
            $t->uuid('key');
            $t->unique(['user_id', 'key']);
            $t->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        foreach (['sandbox_payments', 'sandbox_installments', 'sandbox_payment_plans', 'sandbox_payment_methods', 'portal_preferences', 'visit_receipts', 'page_visits', 'access_events', 'navigation_roles', 'navigation_items'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
