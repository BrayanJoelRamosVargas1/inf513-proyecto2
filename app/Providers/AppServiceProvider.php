<?php

namespace App\Providers;

use App\Domain\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Domain\DocumentScanner::class, \App\Domain\ClamAvScanner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('id', function (string $value): int {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            abort_if($id === false, 404);

            return $id;
        });

        View::composer('layout', function ($view) {
            $u = auth()->user();
            $roles = $u ? Workspace::roles($u) : [];
            $view->with('workspaceRoles', $roles)->with('hasOwnApplications', $u && DB::table('applications')->where('user_id', $u->id)->exists());
        });

        View::share('label', static fn ($s) => [
            'draft' => 'Borrador', 'published' => 'Recepción publicada', 'closed' => 'Recepción cerrada', 'evaluation' => 'Evaluación', 'results' => 'Resultados', 'archived' => 'Archivada',
            'active' => 'Activa', 'withdrawn' => 'Retirada', 'resigned' => 'Renunciada', 'withdrawal' => 'Retiro de postulación', 'renunciation' => 'Renuncia a plaza', 'open' => 'Ventana abierta', 'reviewed' => 'Revisada', 'expired' => 'Vencida', 'cancelled' => 'Cancelada por retiro/renuncia', 'accept' => 'Aceptación DEMO', 'defer' => 'Decisión diferida', 'baseline_at_migration' => 'Estado conocido al migrar', 'pre_amendment_review' => 'Revisión anterior a subsanar',
            'under_review' => 'En revisión: requiere nueva publicación', 'continued' => 'Empate persistente: nueva ronda', 'approved' => 'Aprobada', 'rejected' => 'Rechazada',
            'submitted' => 'Enviada', 'pending' => 'Pendiente', 'enabled' => 'Habilitado', 'disabled' => 'Inhabilitado', 'selected' => 'Seleccionado DEMO', 'not_selected' => 'Fuera de cupo',
            'meets' => 'Cumple', 'fails' => 'No cumple', 'observed' => 'Observado', 'not_applicable' => 'No aplica', 'confirmed' => 'Confirmada', 'corrected' => 'Rectificada', 'scheduled' => 'Examen programado', 'resolved' => 'Resuelto con acta',
            'student' => 'Postulante', 'staff' => 'Personal / docente', 'admin' => 'Administrador técnico', 'authority' => 'Autoridad', 'reviewer' => 'Revisor', 'evaluator' => 'Evaluador',
        ][$s] ?? $s);
    }
}
