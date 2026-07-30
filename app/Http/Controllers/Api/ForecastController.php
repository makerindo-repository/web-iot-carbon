<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\ForecastPrediction;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    /**
     * GET /api/forecasts?device_id=&target=&limit=
     *
     * Menyajikan prediksi yang sudah dihasilkan oleh jadwal forecasting
     * (queue "ai-forecast"), BUKAN menjalankan inferensi langsung pada
     * permintaan — sesuai catatan integrasi pada README model bundle.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|string',
            'target' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $device = Device::where('device_code', $validated['device_id'])->first()
            ?? Device::find($validated['device_id']);

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => "Node dengan ID {$validated['device_id']} tidak ditemukan.",
                'data' => [],
            ], 404);
        }

        $query = ForecastPrediction::where('device_id', $device->id)
            ->whereNotNull('predicted_value')
            ->orderBy('created_at', 'desc');

        if (! empty($validated['target'])) {
            $query->where('target_metric', $validated['target']);
        }

        $predictions = $query->limit($validated['limit'] ?? 50)->get();

        return response()->json([
            'success' => true,
            'data' => $predictions->map(fn ($p) => [
                'target_metric' => $p->target_metric,
                'model_used' => $p->model_used,
                'horizon_hours' => $p->horizon_hours,
                'predicted_value' => $p->predicted_value !== null ? (float) $p->predicted_value : null,
                'predicted_for' => $p->predicted_for?->toIso8601String(),
                'created_at' => $p->created_at->toIso8601String(),
                'assumptions_version' => $p->assumptions_version,
            ]),
            'assumptions_note' => 'Sebagian fitur input prediksi (baseline karbon organik tanah/SOC) memakai asumsi tetap yang didokumentasikan pada docs/deploy_model_bundle/FORECAST_LIVE_ASSUMPTIONS.md, bukan hasil pengukuran langsung di lapangan.',
        ]);
    }
}
