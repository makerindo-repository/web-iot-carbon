<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbonDailyStock;
use App\Models\Device;
use App\Models\Planting;
use App\Services\CarbonFluxService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NodeController extends Controller
{
    public function index()
    {
        $devices = Device::with(['landPlot', 'garden.plant', 'garden.komoditi'])->get();

        return response()->json($devices->map(function ($d) {
            return $this->formatDevice($d);
        }));
    }

    public function show($id)
    {
        $device = Device::with(['landPlot', 'garden.plant', 'garden.komoditi'])
            ->where('device_code', $id)
            ->firstOrFail();

        return response()->json($this->formatDevice($device));
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

        return response()->json($this->formatDevice($device), 201);
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

        return response()->json($this->formatDevice($device));
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

    private function formatDevice($d)
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

        return [
            'db_id' => $d->id, // Real database ID
            'id' => $d->device_code, // String code for display
            'name' => $d->name ?? 'Node '.$d->device_code,
            'location' => $d->location ?? ($d->garden?->garden_name ?? ($d->landPlot?->plot_name ?? 'Unknown')),
            'coords' => [(float) $latitude, (float) $longitude],
            'altitude' => (float) ($d->altitude ?? 0),
            'status' => $d->device_status === 'online' ? 'online' : ($d->device_status === 'warning' ? 'warning' : 'offline'),
            'battery' => 85,
            'rssi' => -65,
            'lastSeen' => $d->last_seen_at ? Carbon::parse($d->last_seen_at)->toIso8601String() : null,
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
        ];
    }
}
