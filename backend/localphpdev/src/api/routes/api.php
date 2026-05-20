<?php

use App\Http\Controllers\CasConsoleController;
use App\Http\Controllers\CasLogController;
use App\Http\Controllers\OpenApiController;
use App\Http\Middleware\RequireApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware(RequireApiKey::class)->group(function (): void {
    // Clovek A:
    // - /cas/*       CAS konzola a reset session
    // - /logs*       logy CAS poziadaviek a CSV export
    // - /openapi*    OpenAPI JSON a dynamicky generovane PDF
    Route::post('/cas/execute', [CasConsoleController::class, 'execute']);
    Route::post('/cas/reset', [CasConsoleController::class, 'reset']);
    Route::get('/logs', [CasLogController::class, 'index']);
    Route::get('/logs/export.csv', [CasLogController::class, 'exportCsv']);
    Route::get('/openapi.json', [OpenApiController::class, 'json']);
    Route::get('/openapi.pdf', [OpenApiController::class, 'pdf']);

    // Clovek B:
    // - /simulations/*  vypocty animacii
    // - /statistics*    statistiky pouzitia animacii
    Route::post('/simulations/inverted-pendulum', [\App\Http\Controllers\SimulationController::class, 'invertedPendulum']);
    Route::post('/simulations/ball-beam', [\App\Http\Controllers\SimulationController::class, 'ballBeam']);
    Route::get('/statistics', [\App\Http\Controllers\StatisticsController::class, 'index']);
    Route::get('/statistics/{simulation}', [\App\Http\Controllers\StatisticsController::class, 'show']);
});
