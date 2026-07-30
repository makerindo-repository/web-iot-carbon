<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiInsight;
use App\Models\CciAnalytic;
use App\Models\Device;
use App\Models\IotReading;
use App\Services\AiInsightService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AiInsightController extends Controller
{
    private const WINDOWS = ['24h' => 1, '7d' => 7, '30d' => 30];

    private const LABELS = [
        '24h' => '24 jam terakhir',
        '7d' => '7 hari terakhir',
        '30d' => '30 hari terakhir',
    ];

    /**
     * POST /api/ai-insight/generate
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'node_id' => 'required|string',
            'time_range' => 'nullable|string|in:24h,7d,30d',
            'force_rule_based' => 'nullable|boolean',
        ]);

        $device = Device::where('device_code', $validated['node_id'])->first()
            ?? Device::find($validated['node_id']);

        if (! $device) {
            return response()->json([
                'error' => "Node dengan ID {$validated['node_id']} tidak ditemukan.",
            ], 404);
        }

        $timeRange = $validated['time_range'] ?? '7d';
        $since = Carbon::now()->subDays(self::WINDOWS[$timeRange] ?? 7);

        $readings = IotReading::where('device_id', $device->id)
            ->where('reading_time', '>=', $since)
            ->orderBy('reading_time', 'desc')
            ->get();

        $latest = $readings->first();
        $totalReadings = $readings->count();

        $cci = CciAnalytic::where('device_id', $device->device_code)
            ->orderBy('created_at', 'desc')
            ->first();

        $context = [
            'nama_node' => $device->name ?? $device->device_code,
            'altitude_m' => (float) ($latest->altitude_m ?? $device->altitude ?? 0),
            'air_pressure_hpa' => (float) ($latest->air_pressure_hpa ?? 1013),
            'air_temperature_c' => (float) ($latest->air_temperature_sensor ?? $readings->avg('air_temperature_sensor') ?? 25),
            'soil_moisture_percent' => (float) ($latest->soil_moisture ?? $readings->avg('soil_moisture') ?? 50),
            'soil_ph' => (float) ($latest->soil_ph ?? $readings->avg('soil_ph') ?? 7),
            'soil_n_mg_kg' => (float) ($latest->soil_n_mg_kg ?? $readings->avg('soil_n_mg_kg') ?? 0),
            'light_lux' => (float) ($latest->light_lux ?? 0),
            'co2_ppm' => (float) ($latest->co2_sensor ?? $readings->avg('co2_sensor') ?? 400),
            'carbon_flux' => (float) ($latest->carbon_flux ?? 0),
            'cci_value' => $cci ? (float) $cci->cci_value : null,
            'cci_status' => $cci?->cci_status,
            'jumlah_pembacaan' => $totalReadings,
            'rentang_waktu' => self::LABELS[$timeRange],
        ];

        $forceRuleBased = filter_var($validated['force_rule_based'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $dataWarning = null;
        $provider = 'rule-based';

        if ($totalReadings === 0) {
            $dataWarning = 'Tidak ditemukan data pembacaan sensor pada rentang waktu yang dipilih untuk node ini; analisis di bawah bersifat umum.';
            [$analisis, $rekomendasi] = AiInsightService::generateRuleBased($context);
        } elseif ($forceRuleBased) {
            [$analisis, $rekomendasi] = AiInsightService::generateRuleBased($context);
        } else {
            $llmResult = AiInsightService::generateViaLlm($context);

            if ($llmResult !== null) {
                [$analisis, $rekomendasi, $provider] = $llmResult;
            } else {
                [$analisis, $rekomendasi] = AiInsightService::generateRuleBased($context);
                $dataWarning = 'Seluruh penyedia layanan AI eksternal (Gemini/Groq/OpenRouter) tidak dapat memproses permintaan saat ini; analisis ditampilkan menggunakan sistem pakar berbasis aturan AgriSense.';
            }
        }

        $insight = AiInsight::create([
            'device_id' => $device->id,
            'time_range' => $timeRange,
            'provider' => $provider,
            'analysis_text' => $analisis,
            'recommendation_json' => $rekomendasi,
            'data_warning' => $dataWarning,
            'total_readings' => $totalReadings,
        ]);

        return response()->json([
            'analisis' => $analisis,
            'rekomendasi' => $rekomendasi,
            'data_warning' => $dataWarning,
            'time_range' => self::LABELS[$timeRange],
            'total_readings' => $totalReadings,
            'generated_at' => $insight->created_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d/m/Y H:i').' WIB',
            'provider' => $provider,
        ]);
    }

    /**
     * GET /api/ai-insight/history?node_id=
     */
    public function history(Request $request)
    {
        $validated = $request->validate([
            'node_id' => 'required|string',
        ]);

        $device = Device::where('device_code', $validated['node_id'])->first()
            ?? Device::find($validated['node_id']);

        if (! $device) {
            return response()->json(['success' => false, 'data' => []], 404);
        }

        $items = AiInsight::where('device_id', $device->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'time_range' => $item->time_range,
                'provider' => $item->provider,
                'created_at' => $item->created_at->toIso8601String(),
                'analysis_text' => $item->analysis_text,
                'recommendation_json' => $item->recommendation_json,
            ]);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}
