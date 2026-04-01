<?php

use App\Http\Controllers\ApplicationSettingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RSCDataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/rsc-data', [RSCDataController::class, 'handleSensorData']);
Route::get('/application-settings', [ApplicationSettingController::class, 'fetchApplicationSettings']);
Route::post('/payment/callback', [PaymentController::class, 'callback']);