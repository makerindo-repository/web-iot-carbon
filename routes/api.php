<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CciController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GardenController;
use App\Http\Controllers\Api\IotReadingController;
use App\Http\Controllers\Api\LandPlotController;
use App\Http\Controllers\Api\NodeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\BmkgController;

// Auth Routes
Route::post('/login', [AuthController::class, 'login']);

// ═══════════════════════════════════════════════════════════════
//  AgriSense V1.0 REST API —
// ═══════════════════════════════════════════════════════════════

// IoT Data Ingestion
Route::post('/iot/agrisense/readings', [IotReadingController::class, 'storeReading']);

// Internal BMKG Sync
Route::get('/internal/bmkg/sync', [BmkgController::class, 'sync']);

// Dashboard & Data
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Superuser Only (Account & System Management)
    Route::middleware('role:superuser')->group(function () {
        Route::get('/users', [SystemController::class, 'getUsers']);
        Route::post('/users', [SystemController::class, 'createUser']);
        Route::put('/users/{id}', [SystemController::class, 'updateUser']);
        Route::delete('/users/{id}', [SystemController::class, 'deleteUser']);
        Route::get('/settings', [SystemController::class, 'getSettings']);
        Route::post('/settings', [SystemController::class, 'updateSettings']);
    });

    // Superuser & Dosen (Operational & IoT Management - Write/Modify Access)
    Route::middleware('role:superuser,dosen')->group(function () {
        Route::post('/nodes', [NodeController::class, 'store']);
        Route::put('/nodes/{id}', [NodeController::class, 'update']);
        Route::delete('/nodes/{id}', [NodeController::class, 'destroy']);
        Route::apiResource('/land-plots', LandPlotController::class);
        Route::apiResource('/gardens', GardenController::class);
        Route::get('/reports/export', [ReportController::class, 'exportReport']);
    });

    // 🔓 Mahasiswa, Dosen, & Superuser (Tampilan Monitoring Umum - Read Only Access)
    Route::get('/nodes', [NodeController::class, 'index']);
    Route::get('/nodes/{id}', [NodeController::class, 'show']);
    Route::get('/dashboard/summary', [DashboardController::class, 'getDashboardSummary']);
    Route::get('/readings', [IotReadingController::class, 'getReadings']);
    Route::get('/bmkg', [BmkgController::class, 'getBmkg']);
    Route::get('/cci', [CciController::class, 'getCci']);
    Route::get('/logs', [SystemController::class, 'getLogs']);
    Route::post('/logs/record', [SystemController::class, 'recordLog']);
});
