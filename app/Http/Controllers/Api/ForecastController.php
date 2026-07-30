<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiForecastResult;
use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ForecastController
 *
 * Menyajikan data hasil prediksi model AI ke frontend dashboard.
 * Data berasal dari tabel ai_forecast_results yang diisi oleh
 * background job ProcessAiForecast.
 */
class ForecastController extends Controller
{
    /**
     * GET /api/forecasts
     *
     * Ambil prediksi terbaru untuk semua device atau device tertentu.
     *
     * Query params:
     *  - device_id   : (opsional) filter berdasarkan device_code
     *  - target      : (opsional) "Soil Moisture (%)" atau "pH"
     *  - horizon     : (opsional) 0, 1, 6, atau 24
     *  - model       : (opsional) "svm", "xgboost", atau "lstm"
     *  - limit       : (opsional) jumlah record, default 50
     */
    public function index(Request $request): JsonResponse
    {
        $query = AiForecastResult::with('device')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc');

        // Filter by device code
        if ($request->has('device_id')) {
            $device = Device::where('device_code', $request->device_id)->first();
            if ($device) {
                $query->where('device_id', $device->id);
            } else {
                return response()->json([
                    'success' => true,
                    'count' => 0,
                    'data' => [],
                ]);
            }
        }

        // Filter by target metric
        if ($request->has('target')) {
            $query->where('target_metric', $request->target);
        }

        // Filter by horizon
        if ($request->has('horizon')) {
            $query->where('horizon_hours', (int) $request->horizon);
        }

        // Filter by model
        if ($request->has('model')) {
            $query->where('model_used', $request->model);
        }

        $limit = min((int) $request->get('limit', 50), 200);
        $forecasts = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'count' => $forecasts->count(),
            'data' => $forecasts->map(function ($f) {
                return [
                    'id' => $f->id,
                    'device_code' => $f->device->device_code ?? 'UNKNOWN',
                    'target_metric' => $f->target_metric,
                    'horizon_hours' => $f->horizon_hours,
                    'model_used' => $f->model_used,
                    'predicted_value' => (float) $f->predicted_value,
                    'current_value' => $f->current_value !== null ? (float) $f->current_value : null,
                    'method' => (int) $f->horizon_hours === 0 ? 'model_estimate' : 'calibrated_projection',
                    'predicted_for' => Carbon::parse($f->predicted_for)->toIso8601String(),
                    'input_reading_time' => Carbon::parse($f->input_reading_time)->toIso8601String(),
                    'created_at' => $f->created_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * GET /api/forecasts/latest
     *
     * Ambil prediksi paling baru untuk setiap device aktif.
     * Digunakan oleh dashboard utama untuk menampilkan ringkasan.
     */
    public function latest(): JsonResponse
    {
        $devices = Device::whereIn('device_status', ['online', 'warning'])->get();

        if ($devices->isEmpty()) {
            return response()->json(['success' => true, 'devices' => []]);
        }

        // Satu query untuk semua device (sebelumnya N+1: 1 query per device).
        // Dibatasi 30 hari terakhir (konsisten dgn window default ModelPerformanceController)
        // supaya tabel ai_forecast_results yg terus tumbuh tidak ikut ter-scan penuh;
        // ambil top-75 per device dilakukan di PHP via groupBy setelah fetch tunggal.
        $forecastsByDevice = AiForecastResult::whereIn('device_id', $devices->pluck('id'))
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('device_id');

        $result = [];
        foreach ($devices as $device) {
            $forecasts = ($forecastsByDevice->get($device->id) ?? collect())->take(75);

            if ($forecasts->isEmpty()) {
                continue;
            }

            $deviceData = [
                'device_code' => $device->device_code,
                'device_name' => $device->device_name ?? $device->device_code,
                'last_forecast_at' => $forecasts->first()->created_at->toIso8601String(),
                'predictions' => [],
            ];

            foreach ($forecasts as $f) {
                $deviceData['predictions'][] = [
                    'target' => $f->target_metric,
                    'horizon' => $f->horizon_hours,
                    'model' => $f->model_used,
                    'value' => (float) $f->predicted_value,
                    'current' => $f->current_value !== null ? (float) $f->current_value : null,
                    'method' => (int) $f->horizon_hours === 0 ? 'model_estimate' : 'calibrated_projection',
                    'for' => Carbon::parse($f->predicted_for)->toIso8601String(),
                ];
            }

            $result[] = $deviceData;
        }

        return response()->json([
            'success' => true,
            'devices' => $result,
        ]);
    }
}
