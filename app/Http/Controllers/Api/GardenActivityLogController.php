<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GardenActivityLog;
use Illuminate\Http\Request;

class GardenActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = GardenActivityLog::with('user:id,name');

        if ($request->has('garden_id')) {
            $query->where('garden_id', $request->garden_id);
        }

        return response()->json($query->orderBy('tanggal', 'desc')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'garden_id' => 'required|exists:gardens,id',
            'tanggal' => 'required|date',
            'jenis_aktivitas' => 'required|string',
            'keterangan' => 'nullable|string',
        ]);

        $data['user_id'] = auth()->id(); // Requires auth middleware

        $log = GardenActivityLog::create($data);

        return response()->json($log->load('user:id,name'), 201);
    }

    public function destroy($id)
    {
        $log = GardenActivityLog::findOrFail($id);
        $log->delete();

        return response()->json(['message' => 'Activity log deleted']);
    }
}
