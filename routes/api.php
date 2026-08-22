<?php

use App\Http\Controllers\Api\AboutCardController;
use App\Http\Controllers\Api\AiInsightController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CciController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ForecastController;
use App\Http\Controllers\Api\GardenActivityLogController;
use App\Http\Controllers\Api\GardenController;
use App\Http\Controllers\Api\IotReadingController;
use App\Http\Controllers\Api\KomoditiController;
use App\Http\Controllers\Api\LandPlotController;
use App\Http\Controllers\Api\ModelPerformanceController;
use App\Http\Controllers\Api\NodeController;
use App\Http\Controllers\Api\PlantController;
use App\Http\Controllers\Api\PlantingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\BmkgController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Auth (throttle 30/menit)
Route::post('/login', [AuthController::class, 'login'])->name('login')->middleware('throttle:30,1');
Route::post('/auth/google', [AuthController::class, 'googleLogin'])->middleware('throttle:30,1');

// IoT ingestion (rate limit dual-axis: 60/menit per IP + 20/menit per device_id)
Route::post('/iot/agrisense/readings', [IotReadingController::class, 'storeReading'])
    ->middleware('throttle:iot-device');

// BMKG sync (throttle 5/menit)
Route::get('/internal/bmkg/sync', [BmkgController::class, 'sync'])
    ->middleware('throttle:5,1');

// Healthcheck (DB + Redis)
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        Cache::store('redis')->get('health_check');

        return response()->json(['status' => 'ok'], 200);
    } catch (Throwable $e) {
        return response()->json(['status' => 'error', 'message' => 'Service unavailable'], 503);
    }
});

// Diagnostic debug route (Public for debugging)
Route::get('/test-model-debug', function() {
    try {
        $ctrl = new \App\Http\Controllers\Api\ModelPerformanceController();
        return $ctrl->index();
    } catch (\Throwable $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ], 200);
    }
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Admin only
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [SystemController::class, 'getUsers']);
        Route::post('/users', [SystemController::class, 'createUser'])->middleware('throttle:10,1');
        Route::put('/users/{id}', [SystemController::class, 'updateUser'])->middleware('throttle:10,1');
        Route::delete('/users/{id}', [SystemController::class, 'deleteUser'])->middleware('throttle:10,1');
        Route::post('/settings', [SystemController::class, 'updateSettings']);
        Route::post('/settings/test-email', [SystemController::class, 'sendTestEmail'])->middleware('throttle:3,1');
    });

    // Admin & Operator (write)
    Route::middleware('role:admin,operator')->group(function () {
        Route::post('/nodes', [NodeController::class, 'store']);
        Route::put('/nodes/{id}', [NodeController::class, 'update']);
        Route::delete('/nodes/{id}', [NodeController::class, 'destroy']);
        Route::apiResource('/land-plots', LandPlotController::class)->except(['index', 'show']);
        Route::apiResource('/gardens', GardenController::class)->except(['index', 'show']);
        Route::apiResource('/plantings', PlantingController::class)->except(['index', 'show']);
        // Log aktivitas = catatan audit (immutable): hanya boleh dibuat & dihapus,
        // tidak diedit. Sebelumnya except(['index','show']) mendaftarkan route
        // PUT/PATCH tanpa method update() di controller → 500. only() memperbaikinya.
        Route::apiResource('/garden-activity-logs', GardenActivityLogController::class)->only(['store', 'destroy']);
        Route::get('/reports/export', [ReportController::class, 'exportReport'])->middleware('throttle:10,1');
        Route::get('/logs', [SystemController::class, 'getLogs']);
        Route::post('/logs/record', [SystemController::class, 'recordLog'])->middleware('throttle:30,1');

        // Komoditi (write)
        Route::post('/komoditi', [KomoditiController::class, 'store'])->middleware('throttle:30,1');
        Route::put('/komoditi/{id}', [KomoditiController::class, 'update'])->middleware('throttle:30,1');
        Route::delete('/komoditi/{id}', [KomoditiController::class, 'destroy']);



        // About cards (write)
        Route::get('/about-cards/admin', [AboutCardController::class, 'adminIndex']);
        Route::post('/about-cards', [AboutCardController::class, 'store']);
        Route::put('/about-cards/{id}', [AboutCardController::class, 'update']);
        Route::delete('/about-cards/{id}', [AboutCardController::class, 'destroy']);
    });

    // All roles (read only)
    Route::get('/nodes', [NodeController::class, 'index']);
    Route::get('/nodes/{id}', [NodeController::class, 'show']);
    Route::get('/land-plots', [LandPlotController::class, 'index']);
    Route::get('/land-plots/{id}', [LandPlotController::class, 'show']);
    Route::get('/gardens', [GardenController::class, 'index']);
    Route::get('/gardens/{id}', [GardenController::class, 'show']);
    Route::get('/plantings', [PlantingController::class, 'index']);
    Route::get('/plantings/{id}', [PlantingController::class, 'show']);
    Route::get('/garden-activity-logs', [GardenActivityLogController::class, 'index']);
    Route::get('/dashboard/summary', [DashboardController::class, 'getDashboardSummary']);
    Route::get('/readings', [IotReadingController::class, 'getReadings']);
    Route::get('/plants', [PlantController::class, 'index']);
    Route::get('/komoditi', [KomoditiController::class, 'index']);
    Route::get('/komoditi/categories', [KomoditiController::class, 'categories']);
    Route::get('/komoditi/{id}', [KomoditiController::class, 'show']);
    Route::get('/bmkg', [BmkgController::class, 'getBmkg']);
    Route::get('/cci', [CciController::class, 'getCci']);
    Route::get('/model-performance', [ModelPerformanceController::class, 'index']);
    Route::get('/model-performance/node/{deviceCode}', [ModelPerformanceController::class, 'perNode']);

    Route::post('/ai-insight/generate', [AiInsightController::class, 'generateInsight'])
        ->middleware('throttle:3,1');
    Route::get('/ai-insight/history', [AiInsightController::class, 'getHistory']);
    Route::get('/forecasts', [ForecastController::class, 'index']);
    Route::get('/forecasts/latest', [ForecastController::class, 'latest']);
    Route::get('/settings', [SystemController::class, 'getSettings']);
    Route::get('/about-cards', [AboutCardController::class, 'index']);

    // Profile (semua role)
    Route::put('/profile', [SystemController::class, 'updateProfile']);
    Route::put('/profile/password', [SystemController::class, 'changePassword'])->middleware('throttle:5,1');
});
