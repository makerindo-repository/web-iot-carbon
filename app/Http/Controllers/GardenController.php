<?php

namespace App\Http\Controllers;

use App\Models\Garden;
use App\Models\LandPlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GardenController extends Controller
{
    // Index Kebun
    public function index()
    {
        $gardens = Garden::with('landPlot')->get();
        return view('pages.garden.index', compact('gardens'));
    }

    // Menampilkan Form Tambah Kebun 
    public function create()
    {
        $landPlots = LandPlot::all(); 
        return view('pages.garden.create', compact('landPlots'));
    }

    // Menyimpan Kebun Baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'land_plot_id' => 'required|exists:land_plots,id',
            'garden_code'  => 'required|unique:gardens,garden_code',
            'garden_name'  => 'required|string',
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
            'area_hectare' => 'required|numeric|min:0|max:999999.99',
            'soil_type'    => 'nullable|string',
        ]);

        try {
            $garden = Garden::create($validated);

            activity()
                ->performedOn($garden)
                ->event('create')
                ->causedBy(Auth::user())
                ->log('Menambahkan data kebun: ' . $garden->garden_name);

            return redirect()->route('garden.index')->with('success', 'Data kebun berhasil ditambahkan.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal menyimpan data kebun. Silakan periksa kembali isian Anda atau hubungi administrator.');
        }
    }

    // Menampilkan Form Edit Kebun 
    public function edit(string $id)
    {
        $garden = Garden::findOrFail($id);
        $landPlots = LandPlot::all(); 
        
        return view('pages.garden.edit', compact('garden', 'landPlots'));
    }

    // Update Kebun
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'land_plot_id' => 'required|exists:land_plots,id',
            'garden_code'  => 'required|unique:gardens,garden_code,' . $id,
            'garden_name'  => 'required|string',
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
            'area_hectare' => 'required|numeric|min:0|max:999999.99',
            'soil_type'    => 'nullable|string',
        ]);

        try {
            $garden = Garden::findOrFail($id);
            $garden->update($validated);
            activity()
                ->performedOn($garden)
                ->event('update')
                ->causedBy(Auth::user())
                ->log('Mengubah data kebun: ' . $garden->garden_name);

            return redirect()->route('garden.index')->with('success', 'Data kebun berhasil diperbarui.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui data kebun. Silakan periksa kembali isian Anda atau hubungi administrator.');
        }
    }


    // Hapus Kebun
    public function destroy(string $id)
    {
        $garden = Garden::findOrFail($id);
        $name = $garden->garden_name;
        $garden->delete();

        activity()
            ->performedOn($garden)
            ->event('delete')
            ->causedBy(Auth::user())
            ->log('Menghapus data kebun: ' . $name);

        return redirect()->route('garden.index')->with('success', 'Data kebun berhasil dihapus.');
    }
}
