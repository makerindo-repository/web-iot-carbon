<?php

namespace App\Http\Controllers;

use App\Models\LandPlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LandPlotController extends Controller
{
    // Menampilkan Daftar Lahan
    public function index()
    {
        $landPlots = LandPlot::all();
        return view('pages.land-plot.index', compact('landPlots'));
    }

    // Menampilkan Form Tambah
    public function create()
    {
        return view('pages.land-plot.create');
    }

    // Menyimpan Data Baru
    public function store(Request $request)
    {
        // Validasi input dan range
        $validated = $request->validate([
            'plot_code'    => 'required|unique:land_plots,plot_code',
            'plot_name'    => 'required|string',
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
            'area_hectare' => 'required|numeric|min:0|max:999999.99',
            'soil_type'    => 'nullable|string',
             'polygon'      => 'required|json'
        ]);

        try {
           
            $landPlot = LandPlot::create($validated);
            activity()
                ->performedOn($landPlot)
                ->event('create')
                ->causedBy(Auth::user())
                ->log('Menambahkan data lahan: ' . $landPlot->plot_name);

            return redirect()->route('land-plot.index')->with('success', 'Data lahan berhasil ditambahkan.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput() 
                ->with('error', 'Gagal menyimpan data lahan. Silakan periksa kembali isian Anda atau hubungi administrator.');
        }
    }

    // Menampilkan Form Edit
    public function edit(string $id)
    {
        $landPlot = LandPlot::findOrFail($id);
        return view('pages.land-plot.edit', compact('landPlot'));
    }

    // Memperbarui Data 
    public function update(Request $request, string $id)
    {
        // Validasi Input
        $validated = $request->validate([
            'plot_code'    => 'required|unique:land_plots,plot_code,' . $id,
            'plot_name'    => 'required|string',
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
            'area_hectare' => 'required|numeric|min:0|max:999999.99',
            'soil_type'    => 'nullable|string',
            'polygon'      => 'required|json'
        ]);

        try {
            $landPlot = LandPlot::findOrFail($id);
            
            // Update Data 
            $landPlot->update($validated);
            activity()
                ->performedOn($landPlot)
                ->event('update')
                ->causedBy(Auth::user())
                ->log('Mengubah data lahan: ' . $landPlot->plot_name);

            return redirect()->route('land-plot.index')->with('success', 'Data lahan berhasil diperbarui.');

        } catch (\Exception $e) {
            // Tangani Error Jika Gagal Simpan
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui data lahan. Silakan periksa kembali isian Anda atau hubungi administrator.');
        }
    }

    // Menghapus Data (Destroy)
    public function destroy(string $id)
    {
        $landPlot = LandPlot::findOrFail($id);
        $name = $landPlot->plot_name;
        $landPlot->delete();

        activity()
            ->performedOn($landPlot)
            ->event('delete')
            ->causedBy(Auth::user())
            ->log('Menghapus data lahan: ' . $name);

        return redirect()->route('land-plot.index')->with('success', 'Data lahan berhasil dihapus.');
    }
}
