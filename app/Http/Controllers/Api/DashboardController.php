<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CciAnalytic;
use App\Models\Device;
use App\Models\IotReading;

class DashboardController extends Controller
{
    // Ringkasan dashboard
    public function getDashboardSummary()
    {
        // Aggregate count di DB
        $counts = Device::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN device_status = 'online' THEN 1 ELSE 0 END) as online,
            SUM(CASE WHEN device_status = 'warning' THEN 1 ELSE 0 END) as warning,
            SUM(CASE WHEN device_status = 'offline' THEN 1 ELSE 0 END) as offline
        ")->first();

        $latestReading = IotReading::with('device')->latest('reading_time')->first();
        $latestCci = CciAnalytic::latest()->first();

        return response()->json([
            'nodes' => [
                'total' => (int) $counts->total,
                'online' => (int) $counts->online,
                'warning' => (int) $counts->warning,
                'offline' => (int) $counts->offline,
            ],
            'latest_reading' => $latestReading,
            'latest_cci' => $latestCci ? (float) $latestCci->cci_value : 0,
        ]);
    }
}
