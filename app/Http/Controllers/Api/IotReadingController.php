<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgrisenseSetting;
use App\Models\Device;
use App\Models\IotReading;
use App\Services\AlertNotificationService;
use App\Services\CarbonFluxService;
use App\Services\CciCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class IotReadingController extends Controller
{
    // Histori sensor terbaru
    public function getReadings(Request $request)
    {
        $query = IotReading::with(['device', 'cciAnalytic'])->orderBy('reading_time', 'desc');

        if ($request->has('device_id')) {
            $query->whereHas('device', fn ($q) => $q->where('device_code', $request->device_id));
        }
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('reading_time', [$request->start_date, $request->end_date.' 23:59:59']);
        }

        // Cap 1500 disamakan dgn permintaan polling frontend (App.tsx: /readings?limit=1500).
        // Sebelumnya cap 500 diam-diam memotong data monitoring multi-node.
        $limit = min((int) $request->get('limit', 100), 1500);
        $readings = $query->limit($limit)->get();

        return response()->json($readings->map(function ($r) {
            return [
                'message_id' => $r->message_id ?? 'MSG-'.$r->id,
                'device_id' => $r->device->device_code ?? 'UNKNOWN',
                'timestamp' => Carbon::parse($r->reading_time)->toIso8601String(),
                'location' => [
                    'latitude' => (float) ($r->latitude ?? $r->device->latitude ?? 0),
                    'longitude' => (float) ($r->longitude ?? $r->device->longitude ?? 0),
                    'altitude_m' => (float) ($r->altitude_m ?? $r->device->altitude ?? 0),
                ],
                'carbon_data' => [
                    'co2_ppm' => (float) ($r->co2_sensor ?? 0),
                    'tvoc_ppb' => (float) ($r->tvoc_ppb ?? 0),
                    'ch4_ppm' => (float) ($r->ch4_ppm ?? 0),
                    'no2_ppb' => (float) ($r->no2_ppb ?? 0),
                    'n2o_ppb' => (float) ($r->n2o_ppb ?? 0),
                    'cci_value' => (float) ($r->cciAnalytic->cci_value ?? $r->cci_value ?? 0),
                    'carbon_flux' => (float) ($r->carbon_flux ?? 0),
                ],
                'environment' => [
                    'air_temperature_c' => (float) ($r->air_temperature_sensor ?? 0),
                    'air_humidity_percent' => (float) ($r->air_humidity_sensor ?? 0),
                    'air_pressure_hpa' => (float) ($r->air_pressure_hpa ?? 0),
                    'wind_speed_kmh' => (float) ($r->wind_speed_kmh ?? 0),
                    'light_lux' => (float) ($r->light_lux ?? 0),
                ],
                
                'power' => [
                    'battery_voltage' => (float) ($r->battery_voltage ?? 0),
                    'battery_percent' => (int) ($r->battery_percent ?? 0),
                ],
                'communication' => [
                    'network_type' => $r->network_type ?? 'WiFi',
                    'rssi_dbm' => (int) ($r->signal_strength ?? 0),
                ],
                'status' => [
                    'node_status' => $r->node_status ?? 'online',
                    'sensor_status' => $r->sensor_status ?? 'normal',
                    'firmware_version' => $r->firmware_version ?? ($r->device->firmware_version ?? '1.0.0'),
                ],
            ];
        }));
    }

    // Terima payload IoT 26-field
    public function storeReading(Request $request)
    {
        // Aturan validasi sengaja LONGGAR untuk semua field numerik kecuali 3 yang
        // critical (device_id, location, co2_ppm). Garbage string dari firmware
        // beragam → null (numeric|nullable), bukan 422.
        // Range plausibility check dipindah ke service layer (CciCalculationService)
        // sebagai soft-skip — out-of-range tetap simpan reading untuk audit, tapi
        // skip kalkulasi CCI/CPS-nya.
        $request->validate([
            'device_id' => 'required|string',
            'timestamp' => 'nullable',
            'message_id' => 'nullable|string|max:64',

            // STRICT (location + co2 = primary metric, wajib valid)
            'location.latitude' => 'required|numeric|between:-90,90',
            'location.longitude' => 'required|numeric|between:-180,180',
            'location.altitude_m' => 'nullable|numeric',
            'carbon_data.co2_ppm' => 'required|numeric|min:0|max:100000',

            // BOUNDARY ATTACK DEFENSE (QA Validations)
            'carbon_data.ch4_ppm' => 'nullable|numeric|min:0|max:100000',
            'carbon_data.no2_ppb' => 'nullable|numeric|min:0|max:100000',
            'carbon_data.n2o_ppb' => 'nullable|numeric|min:0|max:100000',
            'environment.cloud_cover_percent' => 'nullable|numeric|min:0|max:100',
            'environment.wind_speed_kmh' => 'nullable|numeric|min:0|max:500',
            'status.ip' => 'nullable|ip',

            // LONGGAR (numeric|nullable, no range — non-numeric akan jadi null)
            'carbon_data.tvoc_ppb' => 'nullable|numeric',
            'environment.air_temperature_c' => 'nullable|numeric',
            'environment.air_humidity_percent' => 'nullable|numeric',
            'environment.air_pressure_hpa' => 'nullable|numeric',
            'environment.light_lux' => 'nullable|numeric',
            'soil_7in1.soil_moisture_percent' => 'nullable|numeric',
            'soil_7in1.soil_temperature_c' => 'nullable|numeric',
            'soil_7in1.soil_ec_ms_cm' => 'nullable|numeric',
            'soil_7in1.soil_ph' => 'nullable|numeric',
            'soil_7in1.soil_n_mg_kg' => 'nullable|numeric',
            'soil_7in1.soil_p_mg_kg' => 'nullable|numeric',
            'soil_7in1.soil_k_mg_kg' => 'nullable|numeric',
            'power.battery_voltage' => 'nullable|numeric',
            'power.battery_percent' => 'nullable|numeric',
            'communication.rssi_dbm' => 'nullable|numeric',
            'communication.network_type' => 'nullable|string|max:20',
            'status.node_status' => 'nullable|string|max:20',
            'status.sensor_status' => 'nullable|string|max:20',
            'status.firmware_version' => 'nullable|string|max:30',
        ]);

        $device = Device::where('device_code', $request->device_id)->first();

        // Tolak device belum terdaftar
        if (! $device) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Perangkat belum didaftarkan di Manajemen Perangkat. Harap daftarkan Device Code ini terlebih dahulu.',
            ], 403);
        }

        $serverTime = now();
        $messageId = $this->makeUniqueMessageId($request->message_id, $device->device_code);

        $reading = IotReading::create([
            'device_id' => $device->id,
            'plot_id' => $device->plot_id,
            'message_id' => $messageId,
            'reading_time' => $serverTime,
            'latitude' => $request->input('location.latitude'),
            'longitude' => $request->input('location.longitude'),
            'altitude_m' => $request->input('location.altitude_m', 0),
            'co2_sensor' => $request->input('carbon_data.co2_ppm'),
            'tvoc_ppb' => $request->input('carbon_data.tvoc_ppb', 0),
            'ch4_ppm' => $request->input('carbon_data.ch4_ppm'),
            'no2_ppb' => $request->input('carbon_data.no2_ppb'),
            'n2o_ppb' => $request->input('carbon_data.n2o_ppb'),
            'air_temperature_sensor' => $request->input('environment.air_temperature_c') ?? 0,
            'air_humidity_sensor' => $request->input('environment.air_humidity_percent') ?? 0,
            'air_pressure_hpa' => $request->input('environment.air_pressure_hpa') ?? 0,
            'cloud_cover_percent' => $request->input('environment.cloud_cover_percent'),
            'wind_speed_kmh' => $request->input('environment.wind_speed_kmh'),
            'light_lux' => $request->input('environment.light_lux'),
            'soil_moisture' => $request->input('soil_7in1.soil_moisture_percent'),
            'soil_temperature' => $request->input('soil_7in1.soil_temperature_c'),
            'soil_ec_ms_cm' => $request->input('soil_7in1.soil_ec_ms_cm'),
            'soil_ph' => $request->input('soil_7in1.soil_ph'),
            'soil_n_mg_kg' => $request->input('soil_7in1.soil_n_mg_kg'),
            'soil_p_mg_kg' => $request->input('soil_7in1.soil_p_mg_kg'),
            'soil_k_mg_kg' => $request->input('soil_7in1.soil_k_mg_kg'),
            'battery_voltage' => $request->input('power.battery_voltage', 0),
            'battery_percent' => $request->input('power.battery_percent', 0),
            'network_type' => $request->input('communication.network_type', 'WiFi'),
            'signal_strength' => $request->input('communication.rssi_dbm', 0),
            'node_status' => $request->input('status.node_status', 'online'),
            'sensor_status' => $request->input('status.sensor_status', 'normal'),
            'firmware_version' => $request->input('status.firmware_version', '1.0.0'),
            'ip_address' => $request->input('status.ip'),
            'soil_organic_carbon' => (float) ($device->landPlot->soc_baseline_gc_m2 ?? 0),
            'carbon_flux' => 0,
            'data_valid' => true,
            'samples' => $this->whitelistSamples($request),
        ]);

        // Evaluasi threshold
        $co2Threshold = (float) (AgrisenseSetting::where('key', 'co2Threshold')->value('value') ?? 1000);
        $tempMax = (float) (AgrisenseSetting::where('key', 'tempMax')->value('value') ?? 35);
        $humidityMin = (float) (AgrisenseSetting::where('key', 'humidityMin')->value('value') ?? 40);

        $oldStatus = $device->device_status;
        $newStatus = 'online';
        if ($reading->co2_sensor > $co2Threshold ||
            $reading->air_temperature_sensor > $tempMax ||
            $reading->air_humidity_sensor < $humidityMin) {
            $newStatus = 'warning';

            // Kirim notifikasi jika baru warning
            if ($oldStatus !== 'warning') {
                app(AlertNotificationService::class)->sendNodeWarning($reading, $co2Threshold, $tempMax, $humidityMin);
            }
        }

        // Update device status (property assignment — device_status & last_seen_at
        // tidak fillable, hanya boleh di-set dari kode aplikasi)
        $device->device_status = $newStatus;
        $device->last_seen_at = now();

        // Update koordinat utama device mengikuti sensor (dengan filter anti-0,0)
        $newLat = (float) $request->input('location.latitude');
        $newLng = (float) $request->input('location.longitude');
        if ($newLat !== 0.0 && $newLng !== 0.0) {
            $device->latitude = $newLat;
            $device->longitude = $newLng;
            if ($request->filled('location.altitude_m')) {
                $device->altitude = (float) $request->input('location.altitude_m');
            }
        }

        $device->save();

        $analyticsStatus = 'completed';
        $cci = null;
        $flux = [
            'carbon_flux' => (float) $reading->carbon_flux,
            'gpp' => 0,
            'npp' => 0,
        ];

        try {
            // Hitung CCI
            $cci = CciCalculationService::calculateAndStore($reading);

            // Hitung Carbon Flux
            $flux = CarbonFluxService::calculateAndStore($reading);
            CarbonFluxService::updateDailyStock($reading);
        } catch (Throwable $e) {
            $analyticsStatus = 'deferred';
            Log::warning('IoT reading accepted but analytics calculation failed', [
                'reading_id' => $reading->id,
                'device_id' => $device->device_code,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data diterima',
            'reading_id' => $reading->id,
            'analytics_status' => $analyticsStatus,
            'cci' => $cci?->cci_value,
            'carbon_flux' => $flux['carbon_flux'],
            'gpp' => $flux['gpp'],
            'npp' => $flux['npp'],
        ], 201);
    }

    private function makeUniqueMessageId(?string $messageId, string $deviceCode): string
    {
        $base = trim((string) $messageId);

        if ($base === '') {
            $base = 'MSG-'.$deviceCode.'-'.now()->format('YmdHisv');
        }

        $base = substr($base, 0, 64);
        if (! IotReading::where('message_id', $base)->exists()) {
            return $base;
        }

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $suffix = '-D'.now()->format('Hisv').'-'.$attempt;
            $candidate = substr($base, 0, 64 - strlen($suffix)).$suffix;

            if (! IotReading::where('message_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        return substr('MSG-'.$deviceCode.'-'.(string) Str::uuid(), 0, 64);
    }

    // Hanya simpan field IoT yang dikenal
    private function whitelistSamples(Request $request): array
    {
        $allowed = [
            'device_id', 'message_id', 'timestamp',
            'location', 'carbon_data', 'environment',
            'soil_7in1', 'power', 'communication', 'status',
        ];

        $filtered = $request->only($allowed);
        $json = json_encode($filtered);

        // Tolak payload > 8KB
        if (strlen($json) > 8192) {
            $filtered = ['device_id' => $request->device_id, '_truncated' => true];
        }

        return $filtered;
    }
}
