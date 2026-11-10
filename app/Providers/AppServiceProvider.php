<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::share('label', static fn ($s) => [
            'draft' => 'Borrador', 'published' => 'Recepción publicada', 'closed' => 'Recepción cerrada', 'evaluation' => 'Evaluación', 'results' => 'Resultados', 'archived' => 'Archivada',
            'under_review' => 'En revisión: requiere nueva publicación', 'continued' => 'Empate persistente: nueva ronda', 'approved' => 'Aprobada', 'rejected' => 'Rechazada',
            'submitted' => 'Enviada', 'pending' => 'Pendiente', 'enabled' => 'Habilitado', 'disabled' => 'Inhabilitado', 'selected' => 'Seleccionado DEMO', 'not_selected' => 'Fuera de cupo',
            'meets' => 'Cumple', 'fails' => 'No cumple', 'observed' => 'Observado', 'not_applicable' => 'No aplica', 'confirmed' => 'Confirmada', 'corrected' => 'Rectificada', 'scheduled' => 'Examen programado', 'resolved' => 'Resuelto con acta',
            'student' => 'Postulante', 'staff' => 'Personal / docente', 'admin' => 'Administrador técnico', 'authority' => 'Autoridad', 'reviewer' => 'Revisor', 'evaluator' => 'Evaluador',
        ][$s] ?? $s);
    }
}
