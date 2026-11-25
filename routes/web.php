<?php

use App\Http\Controllers\ConsolidatedResultsController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GovernanceController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [PortalController::class, 'loginForm'])->name('login');
Route::post('/login', [PortalController::class, 'login'])->middleware('throttle:6,1');
Route::get('/registro', [PortalController::class, 'registerForm']);
Route::post('/registro', [PortalController::class, 'register'])->middleware('throttle:6,1');
Route::middleware('auth')->group(function () {
    Route::get('/convocatorias/{id}/consolidado', [ConsolidatedResultsController::class, 'show']);
    Route::get('/convocatorias/{id}/consolidado/{format}', [ConsolidatedResultsController::class, 'export'])->whereIn('format', ['pdf', 'xlsx']);
    Route::get('/adjudicaciones', [ParticipationController::class, 'awards']);
    Route::get('/postulaciones/{id}/procesos', [ParticipationController::class, 'show']);
    Route::post('/postulaciones/{id}/subsanaciones', [ParticipationController::class, 'open']);
    Route::post('/subsanaciones/{id}/{action}', [ParticipationController::class, 'amendment'])->whereIn('action', ['archivo', 'enviar', 'revisar', 'vencer']);
    Route::post('/postulaciones/{id}/participacion', [ParticipationController::class, 'request']);
    Route::post('/participacion/{id}/decision', [ParticipationController::class, 'decide']);
    Route::post('/postulaciones/{id}/aceptacion', [ParticipationController::class, 'choose']);
    Route::get('/', [PortalController::class, 'home']);
    Route::post('/salir', [PortalController::class, 'logout']);
    Route::get('/convocatorias/{id}', [PortalController::class, 'call']);
    Route::post('/convocatorias', [PortalController::class, 'createCall']);
    Route::post('/convocatorias/{id}/estado', [PortalController::class, 'transition']);
    Route::post('/convocatorias/{id}/editar', [PortalController::class, 'editCall']);
    Route::post('/convocatorias/{id}/ofertas', [PortalController::class, 'saveOffer']);
    Route::post('/ofertas/{id}/postular', [PortalController::class, 'apply']);
    Route::get('/postulaciones/{id}', [PortalController::class, 'application']);
    Route::post('/postulaciones/{id}/{action}', [PortalController::class, 'action'])->whereIn('action', ['archivo', 'enviar', 'revision', 'habilitacion', 'meritos', 'nota', 'calcular']);
    Route::get('/documentos/{id}/detalle', [DocumentController::class, 'show']);
    Route::get('/documentos/{id}/ver', [DocumentController::class, 'preview']);
    Route::get('/documentos/{id}', [DocumentController::class, 'download']);
    Route::get('/ejemplos/documentos/{kind}', [DocumentController::class, 'sample']);
    Route::get('/ayuda', [PortalController::class, 'guide']);
    Route::post('/ofertas/{id}/asignar', [PortalController::class, 'assign']);

    Route::get('/ofertas/{id}/resultados', [PortalController::class, 'results']);
    Route::post('/ofertas/{id}/desempate', [PortalController::class, 'openTie']);
    Route::post('/desempates/{id}/continuar', [GovernanceController::class, 'continueTie']);
    Route::post('/desempates/{id}/resolver', [PortalController::class, 'resolveTie']);
    Route::post('/ofertas/{id}/aprobar', [PortalController::class, 'approve']);
    Route::get('/convocatorias/{id}/auditoria', [PortalController::class, 'audit']);
    Route::get('/convocatorias/{id}/reporte/{format}', [PortalController::class, 'report'])->whereIn('format', ['pdf', 'xlsx']);
    Route::get('/rectificaciones', [GovernanceController::class, 'queue']);
    Route::post('/usuarios/{id}/perfil', [GovernanceController::class, 'profile']);
    Route::post('/permisos/{id}/revocar', [GovernanceController::class, 'revoke']);
    Route::post('/asignaciones/{id}/reasignar', [GovernanceController::class, 'reassign']);
    Route::get('/postulaciones/{id}/rectificaciones', [GovernanceController::class, 'corrections']);
    Route::post('/postulaciones/{id}/rectificaciones', [GovernanceController::class, 'requestCorrection']);
    Route::post('/rectificaciones/{id}/decision', [GovernanceController::class, 'decide']);
    Route::get('/usuarios', [PortalController::class, 'users']);
    Route::post('/usuarios', [PortalController::class, 'saveUser']);
    Route::post('/permisos', [PortalController::class, 'membership']);
    Route::post('/categorias', [PortalController::class, 'category']);
    Route::post('/categorias/{id}/editar', [PortalController::class, 'editCategory']);
});

Route::get('/p2/{path?}', fn () => view('spa'))->where('path', '.*');
