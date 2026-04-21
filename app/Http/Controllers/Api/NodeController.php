<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NodeController extends Controller
{
    public function index()
    {
        $devices = Device::with(['landPlot', 'garden'])->get();

        return response()->json($devices->map(function ($d) {
            return $this->formatDevice($d);
        }));
    }

    public function show($id)
    {
        $device = Device::where('device_code', $id)->firstOrFail();

        return response()->json($this->formatDevice($device));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string|unique:devices,device_code',
            'name' => 'nullable|string',
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'altitude' => 'nullable|numeric',
            'lahanId' => 'nullable|exists:land_plots,id',
            'garden_id' => 'nullable|exists:gardens,id',
            'firmware_version' => 'nullable|string',
        ]);

        $device = Device::create([
            'device_code' => $validated['id'],
            'plot_id' => $validated['lahanId'] ?? null,
            'garden_id' => $validated['garden_id'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'altitude' => $validated['altitude'] ?? null,
            'device_status' => 'offline',
            'firmware_version' => $validated['firmware_version'] ?? '1.0.0',
        ]);

        activity()->performedOn($device)->log("Mendaftarkan Node baru: {$device->device_code}");

        return response()->json($this->formatDevice($device), 201);
    }

    public function update(Request $request, $id)
    {
        // Smart Find: Try numeric ID first, then device_code
        $device = Device::find($id);
        if (! $device) {
            $device = Device::where('device_code', $id)->first();
        }

        if (! $device) {
            return response()->json(['message' => "Node dengan ID/Kode $id tidak ditemukan"], 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string',
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'altitude' => 'nullable|numeric',
            'lahanId' => 'nullable',
            'gardenId' => 'nullable',
            'firmware_version' => 'nullable|string',
        ]);

        $device->update([
            'plot_id' => $request->lahanId ?: $device->plot_id,
            'garden_id' => $request->gardenId ?: $device->garden_id,
            'latitude' => $request->latitude ?? $device->latitude,
            'longitude' => $request->longitude ?? $device->longitude,
            'altitude' => $request->altitude ?? $device->altitude,
            'firmware_version' => $request->firmware_version ?? $device->firmware_version,
        ]);

        return response()->json($this->formatDevice($device));
    }

    public function destroy($id)
    {
        // Smart Find: Try numeric ID first, then device_code
        $device = Device::find($id) ?: Device::where('device_code', $id)->first();

        if (! $device) {
            return response()->json(['message' => "Gagal menghapus: Node $id tidak ditemukan"], 404);
        }

        $device->delete();

        return response()->json(['message' => 'Node berhasil dihapus']);
    }

    private function formatDevice($d)
    {
        return [
            'db_id' => $d->id, // Real database ID
            'id' => $d->device_code, // String code for display
            'name' => $d->name ?? 'Node '.$d->device_code,
            'location' => $d->location ?? ($d->landPlot?->plot_name ?? 'Unknown'),
            'coords' => [(float) $d->latitude, (float) $d->longitude],
            'altitude' => (float) ($d->altitude ?? 0),
            'status' => $d->device_status === 'online' ? 'online' : ($d->device_status === 'warning' ? 'warning' : 'offline'),
            'battery' => 85,
            'rssi' => -65,
            'lastSeen' => $d->last_seen_at ? Carbon::parse($d->last_seen_at)->toIso8601String() : null,
            'firmware_version' => $d->firmware_version ?? '1.0.0',
            'lahanId' => $d->plot_id ? (string) $d->plot_id : '',
            'gardenId' => $d->garden_id ? (string) $d->garden_id : '',
        ];
    }
}
