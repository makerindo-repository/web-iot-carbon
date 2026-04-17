<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\IotReading;
use App\Services\CciCalculationService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class IotReadingController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    //  GET /api/agrisense/readings — Histori sensor terbaru
    // ═══════════════════════════════════════════════════════════
    public function getReadings(Request $request)
    {
        $query = IotReading::with('device')->orderBy('reading_time', 'desc');

        if ($request->has('device_id')) {
            $query->whereHas('device', fn($q) => $q->where('device_code', $request->device_id));
        }
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('reading_time', [$request->start_date, $request->end_date . ' 23:59:59']);
        }

        $readings = $query->limit($request->get('limit', 100))->get();

        return response()->json($readings->map(function ($r) {
            return [
                'message_id' => $r->message_id ?? 'MSG-' . $r->id,
                'device_id'  => $r->device->device_code ?? 'UNKNOWN',
                'timestamp'  => Carbon::parse($r->reading_time)->toIso8601String(),
                'location'   => [
                    'latitude'   => (float)($r->latitude ?? $r->device->latitude ?? 0),
                    'longitude'  => (float)($r->longitude ?? $r->device->longitude ?? 0),
                    'altitude_m' => (float)($r->altitude_m ?? $r->device->altitude ?? 0),
                ],
                'carbon_data' => [
                    'co2_ppm'   => (float)($r->co2_sensor ?? 0),
                    'tvoc_ppb'  => (float)($r->tvoc_ppb ?? 0),
                ],
                'environment' => [
                    'air_temperature_c'    => (float)($r->air_temperature_sensor ?? 0),
                    'air_humidity_percent'  => (float)($r->air_humidity_sensor ?? 0),
                    'air_pressure_hpa'     => (float)($r->air_pressure_hpa ?? 0),
                    'light_lux'            => (float)($r->light_lux ?? 0),
                ],
                'soil_7in1' => [
                    'soil_moisture_percent' => (float)($r->soil_moisture ?? 0),
                    'soil_temperature_c'    => (float)($r->soil_temperature ?? 0),
                    'soil_ec_ms_cm'         => (float)($r->soil_ec_ms_cm ?? 0),
                    'soil_ph'               => (float)($r->soil_ph ?? 0),
                    'soil_n_mg_kg'          => (float)($r->soil_n_mg_kg ?? 0),
                    'soil_p_mg_kg'          => (float)($r->soil_p_mg_kg ?? 0),
                    'soil_k_mg_kg'          => (float)($r->soil_k_mg_kg ?? 0),
                ],
                'power' => [
                    'battery_voltage'  => (float)($r->battery_voltage ?? 0),
                    'battery_percent'  => (int)($r->battery_percent ?? 0),
                ],
                'communication' => [
                    'network_type' => $r->network_type ?? 'WiFi',
                    'rssi_dbm'     => (int)($r->signal_strength ?? 0),
                ],
                'status' => [
                    'node_status'      => $r->node_status ?? 'online',
                    'sensor_status'    => $r->sensor_status ?? 'normal',
                    'firmware_version' => $r->firmware_version ?? ($r->device->firmware_version ?? '1.0.0'),
                ],
            ];
        }));
    }

    // ═══════════════════════════════════════════════════════════
    //  POST /api/iot/agrisense/readings — Terima payload 26-field
    // ═══════════════════════════════════════════════════════════
    public function storeReading(Request $request)
    {
        $request->validate([
            'device_id'                       => 'required|string',
            'timestamp'                       => 'required',
            'location.latitude'               => 'required|numeric|between:-90,90',
            'location.longitude'              => 'required|numeric|between:-180,180',
            'carbon_data.co2_ppm'             => 'required|numeric|min:0',
        ]);

        $device = Device::where('device_code', $request->device_id)->first();
        
        // Mode Terbatas (Strict): Tolak jika perangkat belum didaftarkan di dashboard
        if (!$device) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akses ditolak: Perangkat belum didaftarkan di Manajemen Perangkat. Harap daftarkan Device Code ini terlebih dahulu.'
            ], 403);
        }

        // Rate Limiting (1-Hour Rule): Cegah database bengkak dengan membatasi 1 data/jam/device
        $lastReading = IotReading::where('device_id', $device->id)->latest('created_at')->first();
        if ($lastReading) {
            $minutesSinceLast = $lastReading->created_at->diffInMinutes(now());
            if ($minutesSinceLast < 60) {
                return response()->json([
                    'status'  => 'ignored',
                    'message' => "Data diabaikan: Limit 1 jam/Node. Data terakhir masuk {$minutesSinceLast} menit yang lalu."
                ], 200); // 200 OK agar hardware tidak panik/retry berlebihan
            }
        }

        $reading = IotReading::create([
            'device_id'              => $device->id,
            'plot_id'                => $device->plot_id,
            'message_id'             => $request->message_id ?? 'MSG-' . time(),
            'reading_time'           => Carbon::parse($request->timestamp),
            'latitude'               => $request->input('location.latitude'),
            'longitude'              => $request->input('location.longitude'),
            'altitude_m'             => $request->input('location.altitude_m', 0),
            'co2_sensor'             => $request->input('carbon_data.co2_ppm'),
            'tvoc_ppb'               => $request->input('carbon_data.tvoc_ppb', 0),
            'air_temperature_sensor' => $request->input('environment.air_temperature_c') ?? 0,
            'air_humidity_sensor'    => $request->input('environment.air_humidity_percent') ?? 0,
            'air_pressure_hpa'       => $request->input('environment.air_pressure_hpa') ?? 0,
            'light_lux'              => $request->input('environment.light_lux', 0),
            'soil_moisture'          => $request->input('soil_7in1.soil_moisture_percent', 0),
            'soil_temperature'       => $request->input('soil_7in1.soil_temperature_c', 0),
            'soil_ec_ms_cm'          => $request->input('soil_7in1.soil_ec_ms_cm', 0),
            'soil_ph'                => $request->input('soil_7in1.soil_ph', 0),
            'soil_n_mg_kg'           => $request->input('soil_7in1.soil_n_mg_kg', 0),
            'soil_p_mg_kg'           => $request->input('soil_7in1.soil_p_mg_kg', 0),
            'soil_k_mg_kg'           => $request->input('soil_7in1.soil_k_mg_kg', 0),
            'battery_voltage'        => $request->input('power.battery_voltage', 0),
            'battery_percent'        => $request->input('power.battery_percent', 0),
            'network_type'           => $request->input('communication.network_type', 'WiFi'),
            'signal_strength'        => $request->input('communication.rssi_dbm', 0),
            'node_status'            => $request->input('status.node_status', 'online'),
            'sensor_status'          => $request->input('status.sensor_status', 'normal'),
            'firmware_version'       => $request->input('status.firmware_version', '1.0.0'),
            'soil_organic_carbon'    => 0,
            'carbon_flux'            => 0,
            'data_valid'             => true,
            'samples'                => $request->all(),
        ]);

        // Evaluate threshold settings!
        $co2Threshold = (float)(\App\Models\AgrisenseSetting::where('key', 'co2Threshold')->value('value') ?? 1000);
        $tempMax = (float)(\App\Models\AgrisenseSetting::where('key', 'tempMax')->value('value') ?? 35);
        $humidityMin = (float)(\App\Models\AgrisenseSetting::where('key', 'humidityMin')->value('value') ?? 40);

        $newStatus = 'online';
        if ($reading->co2_sensor > $co2Threshold || 
            $reading->air_temperature_sensor > $tempMax || 
            $reading->air_humidity_sensor < $humidityMin) {
            $newStatus = 'warning';
        }

        // Update device status
        $device->update(['device_status' => $newStatus, 'last_seen_at' => now()]);

        // Auto-calculate CCI
        $cci = CciCalculationService::calculateAndStore($reading);



        return response()->json([
            'status'     => 'success',
            'message'    => 'Data diterima',
            'reading_id' => $reading->id,
            'cci'        => $cci->cci_value,
        ], 201);
    }
}
