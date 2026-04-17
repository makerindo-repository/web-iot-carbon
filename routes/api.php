<?php

use App\Http\Controllers\BmkgController;

use App\Http\Controllers\Api\NodeController;
use App\Http\Controllers\Api\IotReadingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CciController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\LandPlotController;
use App\Http\Controllers\Api\GardenController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



// ═══════════════════════════════════════════════════════════════
//  AgriSense V1.0 REST API —
// ═══════════════════════════════════════════════════════════════

// IoT Data Ingestion 
Route::post('/iot/agrisense/readings', [IotReadingController::class, 'storeReading']);

// Internal BMKG Sync 
Route::get('/internal/bmkg/sync', [BmkgController::class, 'sync']);

// Dashboard & Data 
Route::prefix('agrisense')->group(function () {
    Route::get('/dashboard/summary', [DashboardController::class, 'getDashboardSummary']);
    Route::apiResource('/nodes', NodeController::class);
    Route::get('/readings',          [IotReadingController::class, 'getReadings']);
    Route::get('/bmkg',              [BmkgController::class, 'getBmkg']);
    Route::get('/cci',               [CciController::class, 'getCci']);
    Route::get('/logs',              [SystemController::class, 'getLogs']);
    Route::post('/logs/record',      [SystemController::class, 'recordLog']);
    Route::get('/users',             [SystemController::class, 'getUsers']);
    Route::post('/users',            [SystemController::class, 'createUser']);
    Route::put('/users/{id}',        [SystemController::class, 'updateUser']);
    Route::delete('/users/{id}',     [SystemController::class, 'deleteUser']);
    
    Route::get('/settings',          [SystemController::class, 'getSettings']);
    Route::post('/settings',         [SystemController::class, 'updateSettings']);
    Route::get('/reports/export',    [ReportController::class, 'exportReport']);
    
    // Area Management Data
    Route::apiResource('/land-plots', LandPlotController::class);
    Route::apiResource('/gardens',    GardenController::class);
});