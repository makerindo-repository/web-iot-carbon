<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IotReading;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    //  GET /api/agrisense/reports/export — Export data
    // ═══════════════════════════════════════════════════════════
    public function exportReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ], [
            'start_date.required' => 'Periode waktu (tanggal mulai) wajib diisi untuk export data.',
            'end_date.required' => 'Periode waktu (tanggal akhir) wajib diisi untuk export data.',
        ]);

        $format = $request->get('format', 'csv');
        $type = $request->get('type', 'readings');

        $query = IotReading::with('device')->orderBy('reading_time', 'desc');

        $query->whereBetween('reading_time', [$request->start_date, $request->end_date.' 23:59:59']);
        if ($request->has('device_id')) {
            $query->whereHas('device', fn ($q) => $q->where('device_code', $request->device_id));
        }

        $data = $query->limit(1000)->get();

        if ($format === 'csv') {
            return $this->exportCsv($data, $type);
        }

        // Default: return JSON data for frontend to handle Excel/PDF
        return response()->json([
            'status' => 'success',
            'count' => $data->count(),
            'data' => $data->map(fn ($r) => [
                'device_id' => $r->device->device_code ?? '',
                'timestamp' => Carbon::parse($r->reading_time)->format('Y-m-d H:i:s'),
                'co2_ppm' => $r->co2_sensor,
                'temperature' => $r->air_temperature_sensor,
                'humidity' => $r->air_humidity_sensor,
                'soil_moisture' => $r->soil_moisture,
                'soil_ph' => $r->soil_ph,
                'soil_n' => $r->soil_n_mg_kg,
                'soil_p' => $r->soil_p_mg_kg,
                'soil_k' => $r->soil_k_mg_kg,
            ]),
        ]);
    }

    private function exportCsv($data, $type)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="agrisense_report_'.now()->format('Ymd_His').'.csv"',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Device ID', 'Timestamp', 'CO2 (ppm)', 'Temp (°C)', 'Humidity (%)', 'Soil Moisture (%)', 'pH', 'N (mg/kg)', 'P (mg/kg)', 'K (mg/kg)', 'Battery (%)', 'RSSI (dBm)']);
            foreach ($data as $r) {
                fputcsv($file, [
                    $r->device->device_code ?? '',
                    Carbon::parse($r->reading_time)->format('Y-m-d H:i:s'),
                    $r->co2_sensor, $r->air_temperature_sensor, $r->air_humidity_sensor,
                    $r->soil_moisture, $r->soil_ph,
                    $r->soil_n_mg_kg, $r->soil_p_mg_kg, $r->soil_k_mg_kg,
                    $r->battery_percent, $r->signal_strength,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
