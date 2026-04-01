<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $devices = Device::get();
        return view('pages.device.index', compact('devices'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.device.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:png,jpg,jpeg|max:2048',
            'series' => 'required|string',
            'name' => 'required|string',
            'installation_date' => 'required|date',
            'tipe_koneksi' => 'required|in:lora,gsm,wifi',
            'note' => 'required|string',
            'wifi_ssid' => 'nullable|string',
            'wifi_password' => 'nullable|string',
            'lora_id' => 'nullable|string',
            'lora_channel' => 'nullable|string',
            'lora_net_id' => 'nullable|string',
            'lora_key' => 'nullable|string',
            'gsm_provider' => 'nullable|string',
            'gsm_nomor_kartu' => 'nullable|string',
        ], [
            'photo.required' => 'Foto harus diunggah.',
            'photo.image' => 'File yang diunggah harus berupa gambar.',
            'photo.mimes' => 'Format gambar harus jpeg, png, atau jpg.',
            'photo.max' => 'Ukuran gambar tidak boleh lebih dari 2MB.',
            'series.required' => 'Series harus diisi.',
            'name.required' => 'Nama harus diisi.',
            'installation_date.required' => 'Tanggal pemasangan harus diisi.',
            'installation_date.date' => 'Tanggal pemasangan harus berupa tanggal yang valid.',
            'tipe_koneksi.required' => 'Tipe koneksi harus dipilih.',
            'note.required' => 'Note harus diisi.'
        ]);

        $photo = ImageService::image_intervention($request->file('photo'), 'uploads/devices/', 1 / 1);

        $post = Device::create($request->except('photo') + [
            'image' => $photo,
        ]);

        activity()
            ->performedOn($post)
            ->event('create')
            ->causedBy(Auth::user())
            ->log('Perangkat baru ditambahkan: ' . $request->series);

        return redirect()->route('device.index')->with('success', 'Data perangkat berhasil ditambahkan');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $device = Device::findOrFail($id);
        return view('pages.device.show', compact('device'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $device = Device::findOrFail($id);
        return view('pages.device.edit', compact('device'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'photo' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'series' => 'required|string',
            'name' => 'required|string',
            'installation_date' => 'required|date',
            'tipe_koneksi' => 'required|in:lora,gsm,wifi',
            'note' => 'required|string',
            'wifi_ssid' => 'nullable|string',
            'wifi_password' => 'nullable|string',
            'lora_id' => 'nullable|string',
            'lora_channel' => 'nullable|string',
            'lora_net_id' => 'nullable|string',
            'lora_key' => 'nullable|string',
            'gsm_provider' => 'nullable|string',
            'gsm_nomor_kartu' => 'nullable|string',
        ], [
            'photo.image' => 'File yang diunggah harus berupa gambar.',
            'photo.mimes' => 'Format gambar harus jpeg, png, atau jpg.',
            'photo.max' => 'Ukuran gambar tidak boleh lebih dari 2MB.',
            'series.required' => 'Series harus diisi.',
            'name.required' => 'Nama harus diisi.',
            'installation_date.required' => 'Tanggal pemasangan harus diisi.',
            'installation_date.date' => 'Tanggal pemasangan harus berupa tanggal yang valid.',
            'tipe_koneksi.required' => 'Tipe koneksi harus dipilih.',
            'note.required' => 'Note harus diisi.'
        ]);

        $device = Device::findOrFail($id);
        $beforeUpdate = $device->getOriginal();
        $device->update($request->except('photo'));

        $changes = [];

        if ($request->hasFile('photo')) {
            ImageService::deleteImage($device->image);
            $photo = ImageService::image_intervention($request->file('photo'), 'uploads/devices/', 1 / 1);
            $device->update(['image' => $photo]);
            $changes['image'] = [
                'old' => $beforeUpdate['image'],
                'new' => $photo,
            ];
        }

        foreach ($request->except('photo') as $key => $value) {
            if (array_key_exists($key, $beforeUpdate) &&  $beforeUpdate[$key] !== $value) {
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
            ->log('Perangkat dengan series ' . $device->series . ' berhasil diupdate');

        return redirect()->route('device.index')->with('success', 'Data perangkat berhasil diupdate');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $device = Device::findOrFail($id);
        if ($device->image) {
            ImageService::deleteImage($device->image);
        }
        $device->delete();

        activity()
            ->performedOn($device)
            ->event('delete')
            ->causedBy(Auth::user())
            ->log('Perangkat dihapus: ' . $device->series);

        return redirect()->route('device.index')->with('success', 'Data perangkat berhasil dihapus');
    }
}
