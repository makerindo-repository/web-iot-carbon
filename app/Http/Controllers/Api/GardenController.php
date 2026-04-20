<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Garden;
use Illuminate\Http\Request;

class GardenController extends Controller
{
    public function index()
    {
        return response()->json(Garden::with('landPlot')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'land_plot_id' => 'required|exists:land_plots,id',
            'garden_code' => 'nullable|string',
            'garden_name' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'area_hectare' => 'nullable|numeric',
            'soil_type' => 'nullable|string',
            'plant_types' => 'nullable|string',
            'polygon' => 'nullable'
        ]);

        if (empty($data['garden_code'])) {
            $lastId = Garden::max('id') ?? 0;
            $data['garden_code'] = 'G-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
        }

        $garden = Garden::create($data);
        activity()->performedOn($garden)->log("Menambah Kebun/Blok baru: {$garden->garden_name}");
        return response()->json($garden, 201);
    }

    public function update(Request $request, $id)
    {
        $garden = Garden::findOrFail($id);
        
        $data = $request->validate([
            'land_plot_id' => 'sometimes|exists:land_plots,id',
            'garden_code' => 'sometimes|string',
            'garden_name' => 'sometimes|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'area_hectare' => 'nullable|numeric',
            'soil_type' => 'nullable|string',
            'plant_types' => 'nullable|string',
            'polygon' => 'nullable'
        ]);

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
