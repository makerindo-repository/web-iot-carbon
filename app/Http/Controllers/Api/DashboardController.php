<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\IotReading;
use App\Models\CciAnalytic;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    //  GET /api/agrisense/dashboard/summary — Ringkasan dashboard
    // ═══════════════════════════════════════════════════════════
    public function getDashboardSummary()
    {
        $devices = Device::all();
        $latestReading = IotReading::with('device')->latest('reading_time')->first();
        $latestCci = CciAnalytic::latest()->first();

        return response()->json([
            'nodes' => [
                'total'   => $devices->count(),
                'online'  => $devices->where('device_status', 'online')->count(),
                'warning' => $devices->where('device_status', 'warning')->count(),
                'offline' => $devices->where('device_status', 'offline')->count(),
            ],
            'latest_reading' => $latestReading,
            'latest_cci'     => $latestCci ? (float)$latestCci->cci_value : 0,
        ]);
    }
}
