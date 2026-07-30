<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandPlot;
use App\Services\CarbonFluxService;
use App\Services\SoilGridsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LandPlotController extends Controller
{
    public function index()
    {
        return response()->json(LandPlot::select([
            'id', 'plot_code', 'plot_name', 'owner_name', 'address',
            'latitude', 'longitude', 'area_hectare', 'soil_type', 'plant_types', 'keterangan',
            'polygon', 'color', 'created_at', 'updated_at',
        ])->get());
    }

    public function show($id)
    {
        return response()->json(LandPlot::with('gardens')->findOrFail($id));
    }

    public function store(Request $request)
    {
        // Sanitasi payload: konversi string "null" atau "" menjadi null agar lolos validasi tipe numerik/eksistensi
        $request->merge(collect($request->all())->map(function ($value) {
            return ($value === 'null' || $value === '') ? null : $value;
        })->toArray());

        $data = $request->validate([
            'plot_code' => 'nullable|string',
            'plot_name' => 'required|string',
            'owner_name' => 'nullable|string',
            'address' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'area_hectare' => 'required|numeric|min:0|max:999999',
            'soil_type' => 'nullable|string',
            'plant_types' => 'nullable|string',
            'keterangan' => 'nullable|string',
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

        if (empty($data['plot_code'])) {
            $lastId = LandPlot::max('id') ?? 0;
            $data['plot_code'] = 'L-'.str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
        }

        // XSS Sanitization
        $data['plot_name'] = strip_tags($data['plot_name']);
        if (isset($data['owner_name'])) {
            $data['owner_name'] = strip_tags($data['owner_name']);
        }
        if (isset($data['address'])) {
            $data['address'] = strip_tags($data['address']);
        }
        if (isset($data['keterangan'])) {
            $data['keterangan'] = strip_tags($data['keterangan']);
        }
        if (isset($data['plot_code'])) {
            $data['plot_code'] = strip_tags($data['plot_code']);
        }

        $landPlot = LandPlot::create($data);
        activity()->performedOn($landPlot)->log("Menambah Lahan baru: {$landPlot->plot_name}");

        // Auto-fetch SOC baseline dari SoilGrids jika koordinat tersedia
        $this->fetchAndStoreSocBaseline($landPlot);

        return response()->json($landPlot->fresh(), 201);
    }

    public function update(Request $request, $id)
    {
        $landPlot = LandPlot::findOrFail($id);

        // Sanitasi payload
        $request->merge(collect($request->all())->map(function ($value) {
            return ($value === 'null' || $value === '') ? null : $value;
        })->toArray());

        $data = $request->validate([
            'plot_code' => 'sometimes|string',
            'plot_name' => 'sometimes|string',
            'owner_name' => 'nullable|string',
            'address' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'area_hectare' => 'required|numeric|min:0|max:999999',
            'soil_type' => 'nullable|string',
            'plant_types' => 'nullable|string',
            'keterangan' => 'nullable|string',
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
        if (isset($data['plot_name'])) {
            $data['plot_name'] = strip_tags($data['plot_name']);
        }
        if (isset($data['owner_name'])) {
            $data['owner_name'] = strip_tags($data['owner_name']);
        }
        if (isset($data['address'])) {
            $data['address'] = strip_tags($data['address']);
        }
        if (isset($data['keterangan'])) {
            $data['keterangan'] = strip_tags($data['keterangan']);
        }
        if (isset($data['plot_code'])) {
            $data['plot_code'] = strip_tags($data['plot_code']);
        }

        $landPlot->update($data);

        // Re-fetch SOC jika koordinat berubah
        if ($request->has('latitude') || $request->has('longitude')) {
            $this->fetchAndStoreSocBaseline($landPlot);
        }

        return response()->json($landPlot->fresh());
    }

    public function destroy($id)
    {
        $landPlot = LandPlot::findOrFail($id);
        $landPlot->delete();

        return response()->json(['message' => 'Land Plot deleted']);
    }

    /**
     * Ambil SOC baseline dari SoilGrids 2.0 dan simpan ke land plot.
     * Ref: Poggio et al. (2021)
     */
    private function fetchAndStoreSocBaseline(LandPlot $plot): void
    {
        if (! $plot->latitude || ! $plot->longitude) {
            return;
        }

        try {
            $soc = SoilGridsService::fetchSOC((float) $plot->latitude, (float) $plot->longitude);
            $cMax = CarbonFluxService::estimateCMax($soc['soc_gc_m2']);

            $plot->update([
                'soc_baseline_gc_m2' => $soc['soc_gc_m2'],
                'c_max_gc_m2' => $cMax,
                'soc_source' => $soc['source'],
            ]);
        } catch (\Exception $e) {
            Log::warning("SoilGrids fetch failed for plot {$plot->id}: ".$e->getMessage());
        }
    }
}
