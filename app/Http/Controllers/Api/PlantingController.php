<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KomoditiFaseTanam;
use App\Models\Planting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PlantingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $plantings = Planting::with(['garden', 'komoditi', 'device'])->orderBy('created_at', 'desc')->get();

        return response()->json($plantings);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_tanaman' => 'required|string|max:255',
            'garden_id' => 'required|exists:gardens,id',
            'komoditi_id' => 'nullable|exists:komoditi_tanaman,id',
            'device_id' => 'nullable|exists:devices,id',
            'tanggal_tanam' => 'nullable|date',
            'estimasi_panen' => 'nullable|date',
            'status_fase' => 'required|string',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['tanggal_tanam'])) {
            $estimasi = $this->calculateEstimasiPanen($validated['tanggal_tanam'], $validated['komoditi_id']);
            if ($estimasi) {
                $validated['estimasi_panen'] = $estimasi;
            }
        }

        // XSS Sanitization
        if (isset($validated['nama_tanaman'])) {
            $validated['nama_tanaman'] = strip_tags($validated['nama_tanaman']);
        }
        if (isset($validated['status_fase'])) {
            $validated['status_fase'] = strip_tags($validated['status_fase']);
        }

        $planting = Planting::create($validated);
        $planting->load(['garden', 'komoditi', 'device']);

        return response()->json([
            'status' => 'success',
            'message' => 'Tanaman berhasil ditambahkan',
            'data' => $planting,
        ], 201);
    }

    public function show($id)
    {
        $planting = Planting::with(['garden', 'komoditi', 'device'])->find($id);
        if (! $planting) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json($planting);
    }

    public function update(Request $request, $id)
    {
        $planting = Planting::find($id);
        if (! $planting) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'nama_tanaman' => 'sometimes|required|string|max:255',
            'garden_id' => 'sometimes|required|exists:gardens,id',
            'komoditi_id' => 'nullable|exists:komoditi_tanaman,id',
            'device_id' => 'nullable|exists:devices,id',
            'tanggal_tanam' => 'nullable|date',
            'estimasi_panen' => 'nullable|date',
            'status_fase' => 'sometimes|required|string',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['tanggal_tanam'])) {
            $komoditiId = $validated['komoditi_id'] ?? $planting->komoditi_id;
            $estimasi = $this->calculateEstimasiPanen($validated['tanggal_tanam'], $komoditiId);
            if ($estimasi) {
                $validated['estimasi_panen'] = $estimasi;
            }
        }

        // XSS Sanitization
        if (isset($validated['nama_tanaman'])) {
            $validated['nama_tanaman'] = strip_tags($validated['nama_tanaman']);
        }
        if (isset($validated['status_fase'])) {
            $validated['status_fase'] = strip_tags($validated['status_fase']);
        }

        $planting->update($validated);
        $planting->load(['garden', 'komoditi', 'device']);

        return response()->json([
            'status' => 'success',
            'message' => 'Data tanaman berhasil diperbarui',
            'data' => $planting,
        ]);
    }

    public function destroy($id)
    {
        $planting = Planting::find($id);
        if (! $planting) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $planting->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data tanaman berhasil dihapus',
        ]);
    }

    private function calculateEstimasiPanen($tanggalTanam, $komoditiId)
    {
        $komoditiFase = KomoditiFaseTanam::where('komoditi_id', $komoditiId)->first();
        if ($komoditiFase && $komoditiFase->usia_tanam_max) {
            $days = $komoditiFase->usia_tanam_max;
            if (strtolower($komoditiFase->satuan_usia) === 'minggu') {
                $days *= 7;
            } elseif (strtolower($komoditiFase->satuan_usia) === 'bulan') {
                $days *= 30;
            }

            return Carbon::parse($tanggalTanam)->addDays($days)->toDateString();
        }

        return null;
    }
}
