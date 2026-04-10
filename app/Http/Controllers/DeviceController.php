<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\LandPlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceController extends Controller
{
    public function index()
    {
        $devices = Device::get();
        return view('pages.device.index', compact('devices'));
    }

    public function create()
    {
        $landPlots = LandPlot::get();
        return view('pages.device.create', compact('landPlots'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'device_code' => 'required|string|unique:devices,device_code',
            'plot_id' => 'required|exists:land_plots,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'device_status' => 'nullable|string',
        ], [
            'device_code.required' => 'Kode perangkat harus diisi.',
            'device_code.unique' => 'Kode perangkat sudah digunakan.',
            'plot_id.required' => 'Lahan harus dipilih.',
            'plot_id.exists' => 'Lahan tidak valid.',
            'latitude.required' => 'Latitude wajib diisi (klik pada peta).',
            'longitude.required' => 'Longitude wajib diisi (klik pada peta).'
        ]);

        $post = Device::create($request->all());

        activity()
            ->performedOn($post)
            ->event('create')
            ->causedBy(Auth::user())
            ->log('Perangkat baru ditambahkan: ' . $request->device_code);

        return redirect()->route('device.index')->with('success', 'Data perangkat berhasil ditambahkan');
    }

    public function show(string $id)
    {
        $device = Device::findOrFail($id);
        return view('pages.device.show', compact('device'));
    }

    public function edit(string $id)
    {
        $device = Device::findOrFail($id);
        $landPlots = LandPlot::get();
        return view('pages.device.edit', compact('device', 'landPlots'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'device_code' => 'required|string|unique:devices,device_code,' . $id,
            'plot_id' => 'required|exists:land_plots,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'device_status' => 'nullable|string',
        ]);

        $device = Device::findOrFail($id);
        $beforeUpdate = $device->getOriginal();
        
        $device->update($request->only(
            ['device_code', 'plot_id', 'latitude', 'longitude', 'device_status']
        ));

        $changes = [];
        foreach ($request->only(['device_code', 'plot_id', 'latitude', 'longitude', 'device_status']) as $key => $value) {
            if (array_key_exists($key, $beforeUpdate) && $beforeUpdate[$key] != $value) {
                $changes[$key] = [
                    'old' => $beforeUpdate[$key],
                    'new' => $value,
                ];
            }
        }

        activity()
            ->performedOn($device)
            ->event('update')
            ->withProperties(['changes' => $changes])
            ->causedBy(Auth::user())
            ->log('Perangkat ' . $device->device_code . ' berhasil diupdate');

        return redirect()->route('device.index')->with('success', 'Data perangkat berhasil diupdate');
    }

    public function destroy(string $id)
    {
        $device = Device::findOrFail($id);
        $device->delete();

        activity()
            ->performedOn($device)
            ->event('delete')
            ->causedBy(Auth::user())
            ->log('Perangkat dihapus: ' . $device->device_code);

        return redirect()->route('device.index')->with('success', 'Data perangkat berhasil dihapus');
    }
}
