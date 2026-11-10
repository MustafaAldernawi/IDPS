<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\ApiIdentity;
use App\Services\EngineeringService;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware(ApiIdentity::class)->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('catalogs/{catalog}', [CatalogController::class, 'index']);
        Route::post('catalogs/{catalog}', [CatalogController::class, 'store']);
        Route::get('audit-logs', [CatalogController::class, 'audit']);
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::post('users/{id}/roles', [UserController::class, 'assignRole']);
        Route::get('imports', [ImportController::class, 'index']);
        Route::post('imports', [ImportController::class, 'store']);
        Route::get('imports/{id}/preview', [ImportController::class, 'preview']);
        Route::post('imports/{id}/approve', [ImportController::class, 'approve']);
        Route::post('reconciliation-cases/{id}/resolve', [ImportController::class, 'resolve']);
        Route::get('monthly-observations', [ImportController::class, 'observations']);
        Route::post('monthly-observations/{id}/revise', [ImportController::class, 'revise']);
        Route::prefix('ai')->controller(AiController::class)->group(function () {
            Route::get('datasets', 'datasets');
            Route::post('datasets', 'storeDataset');
            Route::get('training-runs', 'trainingRuns');
            Route::post('training-runs', 'train');
            Route::get('models', 'models');
            Route::post('models/{id}/promote', 'promote');
            Route::post('models/{id}/rollback', 'rollback');
            Route::get('forecasts', 'forecasts');
            Route::post('forecasts', 'forecast');
            Route::post('retrain', 'retrain');
        });
        Route::get('dashboard', [EngineeringService::class, 'dashboard']);
        Route::get('kpis', [EngineeringService::class, 'kpis']);
        Route::get('engineering/kpi-definitions', [EngineeringService::class, 'definitions']);
        Route::post('engineering/kpi-definitions', [EngineeringService::class, 'storeDefinition']);
        Route::post('engineering/kpi-definitions/{id}/approve', [EngineeringService::class, 'approveDefinition']);
        Route::get('engineering/annotations', [EngineeringService::class, 'annotations']);
        Route::post('engineering/annotations', [EngineeringService::class, 'storeAnnotation']);
        Route::post('engineering/annotations/{id}/approve', [EngineeringService::class, 'approveAnnotation']);
        Route::get('reports/monthly', [EngineeringService::class, 'export']);
        Route::get('comparisons/actual-target', [EngineeringService::class, 'comparison']);
        Route::get('{resource}', [OperationsController::class, 'index'])->where('resource', 'production-records|crude-feed-records|energy-records|loss-records|chemical-records|quality-samples|blend-batches|plans|events');
        Route::post('{resource}', [OperationsController::class, 'store'])->where('resource', 'production-records|crude-feed-records|energy-records|loss-records|chemical-records|quality-samples|blend-batches|plans|events');
        Route::post('{resource}/{id}/{action}',[OperationsController::class, 'transition'])->where('resource','production-records|crude-feed-records|energy-records|loss-records|chemical-records|quality-samples|blend-batches|plans|events');
    });
});
