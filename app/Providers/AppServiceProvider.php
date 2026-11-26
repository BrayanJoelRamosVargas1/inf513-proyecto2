<?php

namespace App\Providers;

use App\Domain\ClamAvScanner;
use App\Domain\DocumentScanner;
use App\Domain\Selection;
use App\Domain\Workspace;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(DocumentScanner::class, ClamAvScanner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // migrate:fresh, db:wipe y migrate:rollback no se pueden ejecutar contra la base real.
        DB::prohibitDestructiveCommands($this->app->isProduction());
        // B-26: limites generosos que frenan abusos y bucles sin afectar el uso normal ni las lecturas.
        RateLimiter::for('mutations', fn (Request $r) => in_array($r->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            ? Limit::none()
            : Limit::perMinute(180)->by('mutations:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(40)->by('uploads:'.($r->user()?->id ?? $r->ip())));
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
            'active' => 'Activa', 'withdrawn' => 'Retirada', 'resigned' => 'Renunciada', 'withdrawal' => 'Retiro de postulación', 'renunciation' => 'Renuncia a plaza', 'open' => 'Ventana abierta', 'reviewed' => 'Revisada', 'expired' => 'Vencida', 'cancelled' => 'Cancelada por retiro/renuncia', 'accept' => Selection::operationalMode() === 'DEMO' ? 'Aceptación DEMO' : 'Aceptación', 'defer' => 'Decisión diferida', 'baseline_at_migration' => 'Estado conocido al migrar', 'pre_amendment_review' => 'Revisión anterior a subsanar',
            'under_review' => 'En revisión: requiere nueva publicación', 'continued' => 'Empate persistente: nueva ronda', 'approved' => 'Aprobada', 'rejected' => 'Rechazada',
            'submitted' => 'Enviada', 'pending' => 'Pendiente', 'enabled' => 'Habilitado', 'disabled' => 'Inhabilitado', 'selected' => Selection::operationalMode() === 'DEMO' ? 'Seleccionado DEMO' : 'Seleccionado', 'not_selected' => 'Fuera de cupo',
            'meets' => 'Cumple', 'fails' => 'No cumple', 'observed' => 'Observado', 'not_applicable' => 'No aplica', 'confirmed' => 'Confirmada', 'corrected' => 'Rectificada', 'scheduled' => 'Examen programado', 'resolved' => 'Resuelto con acta',
            'student' => 'Postulante', 'staff' => 'Personal / docente', 'admin' => 'Administrador técnico', 'authority' => 'Autoridad', 'reviewer' => 'Revisor', 'evaluator' => 'Evaluador',
        ][$s] ?? $s);
    }
}
