<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbonDailyStock;
use App\Models\Device;
use App\Models\IotReading;
use App\Models\Planting;
use App\Services\CarbonFluxService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NodeController extends Controller
{
    public function index()
    {
        $devices = Device::with(['landPlot', 'garden.plant', 'garden.komoditi'])->get();

        // Pre-fetch last reading per device for real sensor data
        $latestReadingIds = IotReading::selectRaw('MAX(id) as id')
            ->groupBy('device_id')
            ->pluck('id');
        $lastReadings = $latestReadingIds->isNotEmpty()
            ? IotReading::whereIn('id', $latestReadingIds)->get()->keyBy('device_id')
            : collect();

        return response()->json($devices->map(function ($d) use ($lastReadings) {
            return $this->formatDevice($d, $lastReadings->get($d->id));
        }));
    }

    public function show($id)
    {
        $device = Device::with(['landPlot', 'garden.plant', 'garden.komoditi'])
            ->where('device_code', $id)
            ->firstOrFail();

        $lastReading = IotReading::where('device_id', $device->id)
            ->orderByDesc('id')
            ->first();

        return response()->json($this->formatDevice($device, $lastReading));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string|unique:devices,device_code',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'altitude' => 'nullable|numeric',
            'lahanId' => 'nullable|integer|exists:land_plots,id',
            'garden_id' => 'nullable|integer|exists:gardens,id',
            'firmware_version' => 'nullable|string',
        ]);

        $device = Device::create([
            // strip_tags: device_code mengalir ke export CSV (ReportController) &
            // tampilan admin; konsisten dgn pola sanitasi controller lain.
            'device_code' => strip_tags($validated['id']),
            'plot_id' => $validated['lahanId'] ?? null,
            'garden_id' => $validated['garden_id'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'altitude' => $validated['altitude'] ?? null,
            'firmware_version' => $validated['firmware_version'] ?? '1.0.0',
        ]);
        // device_status defaults to 'offline' via migration; field is intentionally
        // not in $fillable so it cannot be set by mass-assignment from request body.

        activity()->performedOn($device)->useLog('Node')->log("Mendaftarkan Node baru: {$device->device_code}");

        return response()->json($this->formatDevice($device, null), 201);
    }

    public function update(Request $request, $id)
    {
        // Smart Find: Try device_code first, then numeric ID
        $device = Device::where('device_code', (string) $id)->first();
        if (! $device && is_numeric($id)) {
            $device = Device::find($id);
        }

        if (! $device) {
            return response()->json(['message' => "Node dengan ID/Kode $id tidak ditemukan"], 404);
        }

        $validated = $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'altitude' => 'nullable|numeric',
            'lahanId' => 'nullable|integer|exists:land_plots,id',
            'gardenId' => 'nullable|integer|exists:gardens,id',
            'firmware_version' => 'nullable|string',
        ]);

        $updateData = [];
        if ($request->has('lahanId')) {
            $updateData['plot_id'] = $request->lahanId;
        }
        if ($request->has('gardenId')) {
            $updateData['garden_id'] = $request->gardenId;
        }
        if ($request->has('latitude')) {
            $updateData['latitude'] = $request->latitude;
        }
        if ($request->has('longitude')) {
            $updateData['longitude'] = $request->longitude;
        }
        if ($request->has('altitude')) {
            $updateData['altitude'] = $request->altitude;
        }
        if ($request->has('firmware_version')) {
            $updateData['firmware_version'] = $request->firmware_version;
        }

        $oldGardenId = $device->garden_id;

        $device->update($updateData);

        if (isset($updateData['garden_id']) && $updateData['garden_id'] != $oldGardenId) {
            Planting::where('device_id', $device->id)
                ->where('garden_id', '!=', $updateData['garden_id'])
                ->update(['device_id' => null]);
        }

        activity()->performedOn($device)->useLog('Node')->log("Mengubah konfigurasi Node: {$device->device_code}");

        $lastReading = IotReading::where('device_id', $device->id)
            ->orderByDesc('id')
            ->first();

        return response()->json($this->formatDevice($device, $lastReading));
    }

    public function destroy($id)
    {
        // Smart Find: Try device_code first, then numeric ID
        $device = Device::where('device_code', (string) $id)->first();
        if (! $device && is_numeric($id)) {
            $device = Device::find($id);
        }

        if (! $device) {
            return response()->json(['message' => "Gagal menghapus: Node $id tidak ditemukan"], 404);
        }

        activity()->useLog('Node')->log("Menghapus Node: {$device->device_code}");

        $device->delete();

        return response()->json(['message' => 'Node berhasil dihapus']);
    }

    private function formatDevice($d, $lastReading = null)
    {
        $latitude = $d->latitude ?? $d->garden?->latitude ?? $d->landPlot?->latitude ?? -6.8500;
        $longitude = $d->longitude ?? $d->garden?->longitude ?? $d->landPlot?->longitude ?? 107.9200;
        $socBaseline = (float) ($d->landPlot?->soc_baseline_gc_m2 ?? 0);
        if ($socBaseline <= 0) {
            $socBaseline = CarbonFluxService::DEFAULT_SOC_BASELINE_GC_M2;
        }
        $cMax = (float) ($d->landPlot?->c_max_gc_m2 ?? 0);
        if ($cMax <= 0) {
            $cMax = CarbonFluxService::estimateCMax($socBaseline);
        }
        $cumulativeNpp = (float) (CarbonDailyStock::where('device_id', $d->id)
            ->orderByDesc('stock_date')
            ->value('cumulative_npp_gc_m2') ?? 0);
        $cCurrent = $socBaseline + $cumulativeNpp;
        $cps = CarbonFluxService::calculateCPS($cCurrent, $cMax);

        // Real sensor data from last iot_reading
        $batteryPercent = $lastReading ? (int) ($lastReading->battery_percent ?? 0) : 0;
        $batteryVoltage = $lastReading ? (float) ($lastReading->battery_voltage ?? 0) : 0;
        $rssi = $lastReading ? (int) ($lastReading->signal_strength ?? -120) : -120;
        $windSpeed = $lastReading ? (float) ($lastReading->wind_speed_kmh ?? 0) : 0;
        $altitude = (float) ($d->altitude ?? $lastReading?->altitude_m ?? 0);

        // Dynamic status check: strictly online (Aktif) or offline (Tidak Aktif)
        $lastSeenTime = $d->last_seen_at ? Carbon::parse($d->last_seen_at) : ($lastReading ? Carbon::parse($lastReading->reading_time) : null);
        $computedStatus = 'offline';
        if ($lastSeenTime) {
            $diffMinutes = $lastSeenTime->diffInMinutes(now());
            if ($diffMinutes <= 30) {
                $computedStatus = 'online';
            } else {
                $computedStatus = 'offline';
            }
        }

        $warningReasons = [];
        if ($lastReading) {
            if (($lastReading->co2_sensor ?? 0) > 1000) $warningReasons[] = 'CO2 Tinggi (>1000 ppm)';
            if (($lastReading->ch4_ppm ?? 0) > 10) $warningReasons[] = 'CH4 Tinggi (>10 ppm)';
            if (($lastReading->no2_ppb ?? 0) > 50) $warningReasons[] = 'NO2 Tinggi (>50 ppb)';
            if (($lastReading->air_temperature_sensor ?? 25) > 35) $warningReasons[] = 'Suhu Tinggi (>35°C)';
            if (($lastReading->air_temperature_sensor ?? 25) < 15) $warningReasons[] = 'Suhu Rendah (<15°C)';
            if (($lastReading->air_humidity_sensor ?? 50) < 30) $warningReasons[] = 'Kelembapan Sangat Rendah (<30%)';
            if ($batteryPercent < 20) $warningReasons[] = 'Baterai Lemah (<20%)';
        }
        $hasWarning = ($computedStatus === 'online') && !empty($warningReasons);

        return [
            'db_id' => $d->id, // Real database ID
            'id' => $d->device_code, // String code for display
            'name' => $d->name ?? 'Node '.$d->device_code,
            'location' => $d->location ?? ($d->garden?->garden_name ?? ($d->landPlot?->plot_name ?? 'Unknown')),
            'coords' => [(float) $latitude, (float) $longitude],
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'altitude' => $altitude,
            'status' => $computedStatus,
            'has_warning' => $hasWarning,
            'warning_reasons' => $warningReasons,
            'battery' => $batteryPercent,
            'battery_percent' => $batteryPercent,
            'battery_voltage' => round($batteryVoltage, 2),
            'rssi' => $rssi,
            'wind_speed' => round($windSpeed, 1),
            'co2_ppm' => (float) ($lastReading->co2_sensor ?? 0),
            'ch4_ppm' => (float) ($lastReading->ch4_ppm ?? 0),
            'no2_ppb' => (float) ($lastReading->no2_ppb ?? 0),
            'lastSeen' => $lastSeenTime ? $lastSeenTime->toIso8601String() : null,
            'last_seen_at' => $lastSeenTime ? $lastSeenTime->toIso8601String() : null,
            'firmware_version' => $d->firmware_version ?? '1.0.0',
            'lahanId' => $d->plot_id ? (string) $d->plot_id : '',
            'gardenId' => $d->garden_id ? (string) $d->garden_id : '',
            'plot_name' => $d->landPlot?->plot_name ?? 'Lahan Utama',
            'garden_name' => $d->garden?->garden_name ?? '',
            'plant_name' => $d->garden?->komoditi?->nama_komoditi ?? $d->garden?->plant?->name ?? $d->garden?->plant_types ?? '',
            'address' => $d->landPlot?->address ?? 'Alamat belum diatur',
            'soc_baseline' => $socBaseline,
            'c_max' => $cMax,
            'cumulative_npp' => $cumulativeNpp,
            'c_current' => $cCurrent,
            'cps' => $cps,
            'kondisi_sekitar' => $d->garden?->kondisi_sekitar ?? 'pertanian_terbuka',
            'radius_konteks_m' => $d->garden?->radius_konteks_m ?? 60,
            'jarak_jalan_m' => $d->garden?->jarak_jalan_m ?? null,
            'dekat_emisi_pabrik' => ($d->garden?->kondisi_sekitar === 'area_industri'),
        ];
    }
}
