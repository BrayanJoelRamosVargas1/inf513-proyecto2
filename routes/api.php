<?php

use App\Http\Controllers\Api\InstitutionalController;
use App\Http\Controllers\Api\InstitutionalRegisterController;
use App\Http\Controllers\Api\ProcessController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\SandboxController;
use App\Http\Controllers\Api\SelectionController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\ConsolidatedResultsController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\SubmissionReceiptController;
use Illuminate\Support\Facades\Route;

// First-party, same-origin JSON API: uses the web session and CSRF middleware, never bearer tokens in localStorage.
Route::prefix('api/v1')->middleware('web')->group(function () {
    Route::get('reference-sources/{key}', [
        ReferenceController::class,
        'download',
    ])->whereIn('key', ['resolucion', 'convocatoria-01', 'convocatoria-02']);
    Route::post('visits/public', [WorkspaceController::class, 'publicVisit'])->middleware('throttle:30,1,public-visits:');
    Route::get('csrf', [SessionController::class, 'csrf'])->middleware('throttle:60,1');
    Route::post('login', [SessionController::class, 'login'])->middleware('throttle:6,1');
    Route::post('register', [SessionController::class, 'register'])->middleware('throttle:6,1');
    Route::middleware(['auth', 'throttle:mutations'])->group(function () {
        Route::get('me', [SessionController::class, 'me']);
        Route::get('institutional/dossiers', [InstitutionalController::class, 'index']);
        Route::post('institutional/dossiers', [InstitutionalController::class, 'create'])->middleware('throttle:20,1');
        Route::get('institutional/dossiers/{id}', [InstitutionalController::class, 'show']);
        Route::post('institutional/dossiers/{id}/sources', [InstitutionalController::class, 'source'])->middleware('throttle:20,1');
        Route::post('institutional/dossiers/{id}/submission', [InstitutionalController::class, 'submit']);
        Route::post('institutional/dossiers/{id}/review', [InstitutionalController::class, 'review']);
        Route::post('institutional/dossiers/{id}/activation', [InstitutionalController::class, 'activation']);
        Route::get('institutional/sources/{id}/download', [InstitutionalController::class, 'download']);
        Route::post('logout', [SessionController::class, 'logout']);
        Route::get('me/navigation', [WorkspaceController::class, 'navigation']);
        Route::match(['get', 'put'], 'me/preferences', [WorkspaceController::class, 'preferences']);
        Route::post('visits', [WorkspaceController::class, 'visit'])->middleware('throttle:60,1');
        Route::get('search', [WorkspaceController::class, 'search'])->middleware('throttle:60,1');
        Route::get('metrics', [WorkspaceController::class, 'metrics']);
        Route::get('calls', [SelectionController::class, 'calls']);
        Route::get('calls/{id}', [SelectionController::class, 'call']);
        Route::get('calls/{id}/institutional-register', [InstitutionalRegisterController::class, 'index']);
        Route::get('calls/{id}/institutional-register/export', [InstitutionalRegisterController::class, 'export']);
        Route::post('calls/{id}/institutional-register', [
            InstitutionalRegisterController::class,
            'store',
        ])->middleware('throttle:20,1');
        Route::post('calls/{id}/transition', [SelectionController::class, 'transition']);
        Route::get('applications', [SelectionController::class, 'applications']);
        Route::get('applications/{id}', [SelectionController::class, 'application']);
        Route::get('applications/{id}/receipt', [SubmissionReceiptController::class, 'download']);
        Route::post('offers/{id}/applications', [SelectionController::class, 'apply']);
        Route::post('applications/{id}/{action}', [SelectionController::class, 'action'])->whereIn('action', [
            'archivo',
            'enviar',
            'revision',
            'habilitacion',
            'meritos',
            'nota',
            'calcular',
        ])->middleware('throttle:uploads');
        Route::get('documents/{id}', [DocumentController::class, 'preview']);
        Route::get('documents/{id}/download', [DocumentController::class, 'download']);
        Route::get('documents/{id}/metadata', [DocumentController::class, 'metadata']);
        Route::get('management', [ProcessController::class, 'management']);
        Route::get('calls/{id}/configuration', [ProcessController::class, 'configuration']);
        Route::get('offers/{id}/ranking', [ProcessController::class, 'ranking']);
        Route::get('applications/{id}/corrections', [ProcessController::class, 'corrections']);
        Route::get('applications/{id}/participation', [ProcessController::class, 'participation']);
        Route::get('awards', [ProcessController::class, 'awards']);
        Route::get('calls/{id}/audit', [ProcessController::class, 'audit']);
        Route::get('calls/{id}/reports/{format}', [ConsolidatedResultsController::class, 'export'])->whereIn('format', ['pdf', 'xlsx']);
        Route::post('calls', [ProcessController::class, 'mutate'])->defaults('operation', 'create-call');
        Route::post('users', [ProcessController::class, 'mutate'])->defaults('operation', 'user');
        Route::post('permissions', [ProcessController::class, 'mutate'])->defaults('operation', 'permission');
        Route::post('categories', [ProcessController::class, 'mutate'])->defaults('operation', 'category');
        Route::post('calls/{id}/configuration', [ProcessController::class, 'mutate'])->defaults('operation', 'edit-call');
        Route::post('calls/{id}/offers', [ProcessController::class, 'mutate'])->defaults('operation', 'offer');
        Route::post('users/{id}/profile', [ProcessController::class, 'mutate'])->defaults('operation', 'profile');
        Route::post('permissions/{id}/revoke', [ProcessController::class, 'mutate'])->defaults('operation', 'revoke');
        Route::post('categories/{id}/configuration', [ProcessController::class, 'mutate'])->defaults('operation', 'edit-category');
        Route::post('offers/{id}/assignments', [ProcessController::class, 'mutate'])->defaults('operation', 'assign');
        Route::post('assignments/{id}/replacement', [ProcessController::class, 'mutate'])->defaults('operation', 'reassign');
        Route::post('applications/{id}/corrections', [ProcessController::class, 'mutate'])->defaults('operation', 'correction');
        Route::post('corrections/{id}/decision', [ProcessController::class, 'mutate'])->defaults('operation', 'decide-correction');
        Route::post('offers/{id}/ties', [ProcessController::class, 'mutate'])->defaults('operation', 'tie');
        Route::post('ties/{id}/resolution', [ProcessController::class, 'mutate'])->defaults('operation', 'resolve-tie');
        Route::post('ties/{id}/continuation', [ProcessController::class, 'mutate'])->defaults('operation', 'continue-tie');
        Route::post('offers/{id}/publications', [ProcessController::class, 'mutate'])->defaults('operation', 'approve');
        Route::post('applications/{id}/amendments', [ProcessController::class, 'mutate'])->defaults('operation', 'open-amendment');
        Route::post('amendments/{id}/documents', [ProcessController::class, 'mutate'])->defaults('operation', 'amendment-upload');
        Route::post('amendments/{id}/submission', [ProcessController::class, 'mutate'])->defaults('operation', 'amendment-submit');
        Route::post('amendments/{id}/review', [ProcessController::class, 'mutate'])->defaults('operation', 'amendment-review');
        Route::post('amendments/{id}/expiration', [ProcessController::class, 'mutate'])->defaults('operation', 'amendment-expire');
        Route::post('applications/{id}/participation-requests', [ProcessController::class, 'mutate'])->defaults('operation', 'participation-request');
        Route::post('participation-requests/{id}/decision', [ProcessController::class, 'mutate'])->defaults('operation', 'participation-decision');
        Route::post('applications/{id}/choices', [ProcessController::class, 'mutate'])->defaults('operation', 'choice');
        Route::get('sandbox', [SandboxController::class, 'index']);
        Route::get('sandbox/report', [SandboxController::class, 'report']);
        Route::post('sandbox/methods', [SandboxController::class, 'method']);
        Route::delete('sandbox/methods/{id}', [SandboxController::class, 'remove']);
        Route::post('sandbox/payments', [SandboxController::class, 'pay']);
        Route::post('sandbox/plans', [SandboxController::class, 'plan']);
    });
});
