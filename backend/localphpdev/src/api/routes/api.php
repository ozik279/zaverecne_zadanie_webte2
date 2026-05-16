<?php

use App\Http\Controllers\CasConsoleController;
use App\Http\Middleware\RequireApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware(RequireApiKey::class)->group(function (): void {
    // Clovek A:
    // - /cas/*       CAS konzola a reset session
    // - /logs*       logy CAS poziadaviek a CSV export
    // - /openapi*    OpenAPI JSON a dynamicky generovane PDF
    Route::post('/cas/execute', [CasConsoleController::class, 'execute']);
    Route::post('/cas/reset', [CasConsoleController::class, 'reset']);

    // Clovek B:
    // - /simulations/*  vypocty animacii
    // - /statistics*    statistiky pouzitia animacii
});

