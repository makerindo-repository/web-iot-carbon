<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Garden;
use App\Services\RoadProximityService;
use Illuminate\Http\Request;

class GardenController extends Controller
{
    public function index()
    {
        // Tidak mengirim data polygon di daftar (performa: mengurangi payload drastis)
        return response()->json(
            Garden::select([
                'id', 'land_plot_id', 'garden_code', 'garden_name',
                'latitude', 'longitude', 'area_hectare', 'soil_type', 'plant_types', 'plant_id', 'komoditi_id', 'keterangan',
                'polygon', 'color', 'created_at', 'updated_at',
            ])->with(['landPlot:id,plot_name,plot_code', 'plant', 'komoditi:id,nama_komoditi,kategori_tanaman,nama_latin,status'])->get()
        );
    }

    public function show($id)
    {
        // Data lengkap termasuk polygon hanya saat melihat detail
        return response()->json(Garden::with(['landPlot', 'plant', 'komoditi'])->findOrFail($id));
    }

    public function store(Request $request)
    {
        // Sanitasi payload: konversi string "null" atau "" menjadi null agar lolos validasi tipe numerik/eksistensi
        $request->merge(collect($request->all())->map(function ($value) {
            return ($value === 'null' || $value === '') ? null : $value;
        })->toArray());

        $data = $request->validate([
            'land_plot_id' => 'required|exists:land_plots,id',
            'garden_code' => 'nullable|string',
            'garden_name' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'area_hectare' => 'required|numeric|min:0|max:999999',
            'soil_type' => 'nullable|string',
            'plant_types' => 'nullable|string',
            'plant_id' => 'nullable|exists:plants,id',
            'komoditi_id' => 'nullable|exists:komoditi_tanaman,id',
            'tanggal_tanam' => 'nullable|date',
            'fase_tanam_saat_ini' => 'nullable|string',
            'keterangan' => 'nullable|string',
            'kondisi_sekitar' => 'nullable|string|in:area_industri,pemukiman_padat,hutan_lindung,pertanian_terbuka,pesisir_pantai',
            'radius_konteks_m' => 'nullable|integer|min:30|max:90',
            'jarak_jalan_m' => 'nullable|integer',
            'polygon' => ['nullable', 'array', 'max:64', function ($attr, $value, $fail) {
                if ($value && strlen(json_encode($value)) > 256 * 1024) {
                    $fail('Polygon JSON melebihi 256KB.');
                }
                if ($value && (! isset($value['type']) || ! isset($value['coordinates']))) {
                    $fail('Polygon harus berformat GeoJSON valid (type + coordinates).');
                }
            }],
            'color' => 'nullable|string|max:20',
        ]);

        if (empty($data['garden_code'])) {
            $lastId = Garden::max('id') ?? 0;
            $data['garden_code'] = 'G-'.str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
        }

        // XSS Sanitization
        if (isset($data['garden_name'])) {
            $data['garden_name'] = strip_tags($data['garden_name']);
        }
        if (isset($data['garden_code'])) {
            $data['garden_code'] = strip_tags($data['garden_code']);
        }
        if (isset($data['keterangan'])) {
            $data['keterangan'] = strip_tags($data['keterangan']);
        }

        // Auto-calculate road proximity if lat/lon provided and jarak_jalan_m is empty
        if (empty($data['jarak_jalan_m']) && ! empty($data['latitude']) && ! empty($data['longitude'])) {
            try {
                $roadService = new RoadProximityService;
                // User requested 100-150m radius, using 150m for finding the nearest road
                $dist = $roadService->getNearestRoadDistance($data['latitude'], $data['longitude'], 150);
                if ($dist !== null) {
                    $data['jarak_jalan_m'] = $dist;
                }
            } catch (\Exception $e) {
                \Log::warning('RoadProximityService failed in Garden store: '.$e->getMessage());
            }
        }

        $garden = Garden::create($data);
        activity()->performedOn($garden)->log("Menambah Kebun/Blok baru: {$garden->garden_name}");

        return response()->json($garden, 201);
    }

    public function update(Request $request, $id)
    {
        $garden = Garden::findOrFail($id);

        // Sanitasi payload
        $request->merge(collect($request->all())->map(function ($value) {
            return ($value === 'null' || $value === '') ? null : $value;
        })->toArray());

        $data = $request->validate([
            'land_plot_id' => 'sometimes|exists:land_plots,id',
            'garden_code' => 'sometimes|string',
            'garden_name' => 'sometimes|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'area_hectare' => 'required|numeric|min:0|max:999999',
            'soil_type' => 'nullable|string',
            'plant_types' => 'nullable|string',
            'plant_id' => 'nullable|exists:plants,id',
            'komoditi_id' => 'nullable|exists:komoditi_tanaman,id',
            'tanggal_tanam' => 'nullable|date',
            'fase_tanam_saat_ini' => 'nullable|string',
            'keterangan' => 'nullable|string',
            'kondisi_sekitar' => 'nullable|string|in:area_industri,pemukiman_padat,hutan_lindung,pertanian_terbuka,pesisir_pantai',
            'radius_konteks_m' => 'nullable|integer|min:30|max:90',
            'jarak_jalan_m' => 'nullable|integer',
            'polygon' => ['nullable', 'array', 'max:64', function ($attr, $value, $fail) {
                if ($value && strlen(json_encode($value)) > 256 * 1024) {
                    $fail('Polygon JSON melebihi 256KB.');
                }
                if ($value && (! isset($value['type']) || ! isset($value['coordinates']))) {
                    $fail('Polygon harus berformat GeoJSON valid (type + coordinates).');
                }
            }],
            'color' => 'nullable|string|max:20',
        ]);

        // XSS Sanitization
        if (isset($data['garden_name'])) {
            $data['garden_name'] = strip_tags($data['garden_name']);
        }
        if (isset($data['garden_code'])) {
            $data['garden_code'] = strip_tags($data['garden_code']);
        }
        if (isset($data['keterangan'])) {
            $data['keterangan'] = strip_tags($data['keterangan']);
        }

        // Auto-calculate road proximity if lat/lon is provided but jarak_jalan_m is not explicitly set
        if (! isset($data['jarak_jalan_m']) && (isset($data['latitude']) || isset($data['longitude']))) {
            $lat = $data['latitude'] ?? $garden->latitude;
            $lon = $data['longitude'] ?? $garden->longitude;

            if ($lat && $lon) {
                try {
                    $roadService = new RoadProximityService;
                    $dist = $roadService->getNearestRoadDistance($lat, $lon, 150);
                    if ($dist !== null) {
                        $data['jarak_jalan_m'] = $dist;
                    }
                } catch (\Exception $e) {
                    \Log::warning('RoadProximityService failed in Garden update: '.$e->getMessage());
                }
            }
        }

        $garden->update($data);

        return response()->json($garden);
    }

    public function destroy($id)
    {
        $garden = Garden::findOrFail($id);
        $garden->delete();

        return response()->json(['message' => 'Garden deleted']);
    }
}
