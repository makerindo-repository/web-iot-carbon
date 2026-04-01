<?php

namespace App\Http\Controllers;

use App\Models\SensorThreshold;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SensorThresholdController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $thresholds = SensorThreshold::get();
        return view('pages.rsc-data.threshold.index', compact('thresholds'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.rsc-data.threshold.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'parameter' => 'required|in:Nitrogen,Phosporus,Kalium,Ec,Ph,Temperature,Humidity|unique:sensor_thresholds,parameter',
            'min' => 'required|numeric',
            'max' => 'required|numeric'
        ], [
            'parameter.required' => 'Parameter harus dipilih.',
            'parameter.in' => 'Parameter tidak valid.',
            'parameter.unique' => 'Parameter :input sudah ada.',
            'min.required' => 'Batas bawah harus diisi.',
            'min.numeric' => 'Batas bawah harus berupa angka.',
            'max.required' => 'Batas atas harus diisi.',
            'max.numeric' => 'Batas atas harus berupa angka.',
        ]);

        $data = [
            'parameter' => $request->parameter,
            'min' => $request->min,
            'max' => $request->max
        ];

        $post = SensorThreshold::create($data);

        activity()
            ->performedOn($post)
            ->event('create')
            ->causedBy(Auth::user())
            ->log('Threshold ditambahkan untuk parameter: ' . $request->parameter);

        return redirect()->route('rsc-data.sensor-threshold.index')->with('success', 'Threshold berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $threshold = SensorThreshold::findOrFail($id);
        return view('pages.rsc-data.threshold.edit', compact('threshold'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'min' => 'required|numeric',
            'max' => 'required|numeric',
        ], [
            'min.required' => 'Batas bawah harus diisi.',
            'min.numeric' => 'Batas bawah harus berupa angka.',
            'max.required' => 'Batas atas harus diisi.',
            'max.numeric' => 'Batas atas harus berupa angka.',
        ]);

        $data = [
            'min' => $request->min,
            'max' => $request->max,
        ];

        $threshold = SensorThreshold::findOrFail($id);
        $beforeUpdate = $threshold->getOriginal();
        $threshold->update($data);

        $changes = [];

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $beforeUpdate) &&  $beforeUpdate[$key] !== $value) {
                $changes[$key] = [
                    'old' => $beforeUpdate[$key],
                    'new' => $value,
                ];
            }
        }

        activity()
            ->performedOn($threshold)
            ->event('update')
            ->withProperties(['changes' => $changes])
            ->causedBy(Auth::user())
            ->log('Threshold untuk parameter ' . $threshold->parameter . ' berhasil diupdate');

        return redirect()->route('rsc-data.sensor-threshold.index')->with('success', 'Data threshold berhasil diupdate.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $threshold = SensorThreshold::findOrFail($id);
        $threshold->delete();

        activity()
            ->performedOn($threshold)
            ->event('delete')
            ->causedBy(Auth::user())
            ->log('Threshold dihapus untuk parameter: ' . $threshold->parameter);

        return redirect()->route('rsc-data.sensor-threshold.index')->with('success', 'Threshold berhasil dihapus.');
    }
}
