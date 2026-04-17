<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CciAnalytic;
use Illuminate\Http\Request;

class CciController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    //  GET /api/agrisense/cci — Data CCI analytics
    // ═══════════════════════════════════════════════════════════
    public function getCci(Request $request)
    {
        $query = CciAnalytic::orderBy('created_at', 'desc');

        if ($request->has('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        $data = $query->limit(100)->get();

        return response()->json([
            'data' => $data->map(function ($c) {
                return [
                    'id'            => $c->id,
                    'device_id'     => $c->device_id,
                    'cci_value'     => (float)$c->cci_value,
                    'cci_status'    => $c->cci_status,
                    'model_version' => $c->model_version,
                    'notes'         => $c->notes,
                    'timestamp'     => $c->created_at->toIso8601String(),
                ];
            }),
            'summary' => [
                'avg_cci'    => round($data->avg('cci_value'), 3),
                'max_cci'    => round($data->max('cci_value'), 3),
                'min_cci'    => round($data->min('cci_value'), 3),
                'total'      => $data->count(),
                'rendah'     => $data->where('cci_status', 'rendah')->count(),
                'sedang'     => $data->where('cci_status', 'sedang')->count(),
                'tinggi'     => $data->where('cci_status', 'tinggi')->count(),
                'kritis'     => $data->where('cci_status', 'kritis')->count(),
            ],
        ]);
    }
}
