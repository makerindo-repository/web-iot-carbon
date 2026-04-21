<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandPlot;
use Illuminate\Http\Request;

class LandPlotController extends Controller
{
    public function index()
    {
        return response()->json(LandPlot::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'plot_code' => 'nullable|string',
            'plot_name' => 'required|string',
            'owner_name' => 'nullable|string',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'area_hectare' => 'nullable|numeric',
            'soil_type' => 'nullable|string',
            'plant_types' => 'nullable|string',
            'polygon' => 'nullable',
        ]);

        if (empty($data['plot_code'])) {
            $lastId = LandPlot::max('id') ?? 0;
            $data['plot_code'] = 'L-'.str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
        }

        $landPlot = LandPlot::create($data);
        activity()->performedOn($landPlot)->log("Menambah Lahan baru: {$landPlot->plot_name}");

        return response()->json($landPlot, 201);
    }

    public function update(Request $request, $id)
    {
        $landPlot = LandPlot::findOrFail($id);

        $data = $request->validate([
            'plot_code' => 'sometimes|string',
            'plot_name' => 'sometimes|string',
            'owner_name' => 'nullable|string',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'area_hectare' => 'nullable|numeric',
            'soil_type' => 'nullable|string',
            'plant_types' => 'nullable|string',
            'polygon' => 'nullable',
        ]);

        $landPlot->update($data);

        return response()->json($landPlot);
    }

    public function destroy($id)
    {
        $landPlot = LandPlot::findOrFail($id);
        $landPlot->delete();

        return response()->json(['message' => 'Land Plot deleted']);
    }
}
