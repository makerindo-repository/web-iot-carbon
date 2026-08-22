<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\IotReading;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ReportController extends Controller
{
    // Export data (CSV/JSON)
    public function exportReport(Request $request)
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');

        $format = $request->get('format', 'csv');
        $type = $request->get('type', 'raw-data');

        // Validasi tanggal untuk tipe berat
        if (in_array($type, ['raw-data', 'analysis'])) {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);
        }

        // Dispatch based on report type
        return match ($type) {
            'raw-data' => $this->exportRawData($request, $format),
            'analysis' => $this->exportAnalysis($request, $format),
            'maintenance' => $this->exportMaintenance($request, $format),
            'system-logs' => $this->exportSystemLogs($request, $format),
            default => $this->exportRawData($request, $format),
        };
    }

    // Export raw sensor data
    private function exportRawData(Request $request, string $format)
    {
        $bounds = $this->dateRangeBounds($request);

        $query = \Illuminate\Support\Facades\DB::table('iot_readings as r')
            ->leftJoin('devices as d', 'r.device_id', '=', 'd.id')
            ->select([
                \Illuminate\Support\Facades\DB::raw("DATE_FORMAT(COALESCE(r.reading_time, r.created_at), '%d/%m/%Y %H:%i:%s') as `Waktu Telemetry`"),
                \Illuminate\Support\Facades\DB::raw("COALESCE(d.id, r.device_id, 1) as `ID Perangkat`"),
                \Illuminate\Support\Facades\DB::raw("COALESCE(d.device_code, 'AGRISENSE-CC-001') as `Kode RH Perangkat`"),
                \Illuminate\Support\Facades\DB::raw("COALESCE(d.name, d.device_code, 'NODE AGRISENSE') as `Nama Perangkat`"),
                \Illuminate\Support\Facades\DB::raw("ROUND(COALESCE(r.wind_speed_kmh, 0), 1) as `Kecepatan Angin (km/h)`"),
                \Illuminate\Support\Facades\DB::raw("
                    CASE 
                        WHEN ROUND(COALESCE(r.wind_direction_deg, (SELECT wind_direction_deg FROM bmkg_readings WHERE plot_id = d.plot_id ORDER BY timestamp_bmkg DESC LIMIT 1), 0) / 45) % 8 = 0 THEN 'Utara (N)'
                        WHEN ROUND(COALESCE(r.wind_direction_deg, (SELECT wind_direction_deg FROM bmkg_readings WHERE plot_id = d.plot_id ORDER BY timestamp_bmkg DESC LIMIT 1), 0) / 45) % 8 = 1 THEN 'Timur Laut (NE)'
                        WHEN ROUND(COALESCE(r.wind_direction_deg, (SELECT wind_direction_deg FROM bmkg_readings WHERE plot_id = d.plot_id ORDER BY timestamp_bmkg DESC LIMIT 1), 0) / 45) % 8 = 2 THEN 'Timur (E)'
                        WHEN ROUND(COALESCE(r.wind_direction_deg, (SELECT wind_direction_deg FROM bmkg_readings WHERE plot_id = d.plot_id ORDER BY timestamp_bmkg DESC LIMIT 1), 0) / 45) % 8 = 3 THEN 'Tenggara (SE)'
                        WHEN ROUND(COALESCE(r.wind_direction_deg, (SELECT wind_direction_deg FROM bmkg_readings WHERE plot_id = d.plot_id ORDER BY timestamp_bmkg DESC LIMIT 1), 0) / 45) % 8 = 4 THEN 'Selatan (S)'
                        WHEN ROUND(COALESCE(r.wind_direction_deg, (SELECT wind_direction_deg FROM bmkg_readings WHERE plot_id = d.plot_id ORDER BY timestamp_bmkg DESC LIMIT 1), 0) / 45) % 8 = 5 THEN 'Barat Daya (SW)'
                        WHEN ROUND(COALESCE(r.wind_direction_deg, (SELECT wind_direction_deg FROM bmkg_readings WHERE plot_id = d.plot_id ORDER BY timestamp_bmkg DESC LIMIT 1), 0) / 45) % 8 = 6 THEN 'Barat (W)'
                        WHEN ROUND(COALESCE(r.wind_direction_deg, (SELECT wind_direction_deg FROM bmkg_readings WHERE plot_id = d.plot_id ORDER BY timestamp_bmkg DESC LIMIT 1), 0) / 45) % 8 = 7 THEN 'Barat Laut (NW)'
                        ELSE 'Utara (N)'
                    END as `Arah Angin (Stasiun BMKG)`
                "),
                \Illuminate\Support\Facades\DB::raw("ROUND(COALESCE(r.latitude, d.latitude, -6.830000), 6) as `Latitude`"),
                \Illuminate\Support\Facades\DB::raw("ROUND(COALESCE(r.longitude, d.longitude, 107.910000), 6) as `Longitude`"),
                \Illuminate\Support\Facades\DB::raw("ROUND(COALESCE(NULLIF(r.altitude_m, 0), NULLIF(d.altitude, 0), 720), 0) as `Elevasi (MDPL)`"),
                \Illuminate\Support\Facades\DB::raw("CONCAT(COALESCE(r.battery_percent, 85), '% (', ROUND(COALESCE(r.battery_voltage, 12.4), 2), 'V)') as `Baterai & Tegangan`"),
                \Illuminate\Support\Facades\DB::raw("ROUND(COALESCE(r.co2_sensor, 0), 1) as `CO2 (ppm)`"),
                \Illuminate\Support\Facades\DB::raw("ROUND(COALESCE(r.ch4_ppm, 0), 1) as `CH4 (ppm)`"),
                \Illuminate\Support\Facades\DB::raw("ROUND(COALESCE(r.no2_ppb, 0), 1) as `N₂O (ppb)`"),
                \Illuminate\Support\Facades\DB::raw("ROUND(COALESCE(r.air_temperature_sensor, 0), 1) as `Suhu Udara (°C)`"),
                \Illuminate\Support\Facades\DB::raw("ROUND(COALESCE(r.air_humidity_sensor, 0), 1) as `Kelembapan Udara (%)`"),
            ])
            ->orderBy(\Illuminate\Support\Facades\DB::raw("COALESCE(r.reading_time, r.created_at)"), 'desc');

        if ($bounds) {
            $query->where(function ($q) use ($bounds) {
                $q->whereBetween('r.reading_time', $bounds)
                  ->orWhereBetween('r.created_at', $bounds);
            });
        }
        if ($request->filled('device_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('d.device_code', $request->device_id)
                  ->orWhere('r.device_id', $request->device_id);
            });
        }

        // Limit maks 25k baris agar ekspor cepat dan hemat memori
        $mapped = $query->limit(25000)->get();

        // Convert stdClass list to array format for JSON/CSV response
        $mappedArray = collect($mapped)->map(fn($item) => (array) $item);

        return $this->respondWithFormat($mappedArray, $format, 'raw-data', $mappedArray->count());
    }

    // Export agregasi per device
    private function exportAnalysis(Request $request, string $format)
    {
        $data = $this->getFilteredReadings($request);

        // Group by device and calculate aggregates
        $grouped = $data->groupBy(fn ($r) => $r->device?->device_code ?? 'UNKNOWN');

        $mapped = $grouped->map(function ($readings, $deviceCode) {
            $device = $readings->first()->device;
            $count = $readings->count();

            return [
                'ID Perangkat' => $device?->id ?? 'N/A',
                'Kode RH Perangkat' => $deviceCode,
                'Nama Perangkat' => $device?->name ?? $deviceCode,
                'Lahan Induk' => $device?->landPlot?->plot_name ?? 'N/A',
                'Jumlah Pembacaan' => $count,
                'Periode Awal' => Carbon::parse($readings->min('reading_time'))->format('d/m/Y H:i'),
                'Periode Akhir' => Carbon::parse($readings->max('reading_time'))->format('d/m/Y H:i'),
                'Rata-rata CO2 (ppm)' => round($readings->avg('co2_sensor'), 1),
                'Min CO2' => round($readings->min('co2_sensor'), 1),
                'Max CO2' => round($readings->max('co2_sensor'), 1),
                'Rata-rata CH4 (ppm)' => round($readings->avg('ch4_ppm'), 1),
                'Rata-rata N₂O (ppb)' => round($readings->avg('no2_ppb'), 1),
                'Rata-rata Suhu (°C)' => round($readings->avg('air_temperature_sensor'), 1),
                'Rata-rata Kelembapan (%)' => round($readings->avg('air_humidity_sensor'), 1),
                'Rata-rata Kecepatan Angin (km/h)' => round($readings->avg('wind_speed_kmh'), 1),
                'Rata-rata Baterai (%)' => round($readings->avg('battery_percent'), 0),
            ];
        })->values();

        return $this->respondWithFormat($mapped, $format, 'analysis', $data->count());
    }

    // Export status perangkat
    private function exportMaintenance(Request $request, string $format)
    {
        $devices = Device::with(['garden.plant', 'garden.komoditi', 'landPlot'])
            ->when($request->filled('device_id'), fn ($query) => $query->where('device_code', $request->device_id))
            ->orderBy('device_code', 'asc')
            ->get();

        $bounds = $this->dateRangeBounds($request);
        $latestIds = IotReading::selectRaw('MAX(id) as id')
            ->whereIn('device_id', $devices->pluck('id'))
            ->when($bounds, fn ($query) => $query->whereBetween('reading_time', $bounds))
            ->groupBy('device_id')
            ->pluck('id');
        $latestReadings = IotReading::whereIn('id', $latestIds)->get()->keyBy('device_id');

        $mapped = $devices->map(function ($d) use ($latestReadings) {
            $lastReading = $latestReadings->get($d->id);
            $batPercent = $lastReading?->battery_percent ?? $d->battery_percent ?? 85;
            $batVolt = round($lastReading?->battery_voltage ?? $d->battery_voltage ?? 12.4, 2);

            return [
                'ID Perangkat' => $d->id,
                'Kode RH Perangkat' => $d->device_code,
                'Nama Perangkat' => $d->name ?? $d->device_code,
                'Lahan Induk' => $d->landPlot?->plot_name ?? 'N/A',
                'Status Node' => ucfirst($d->status ?? 'Aktif'),
                'Baterai & Tegangan' => "{$batPercent}% ({$batVolt}V)",
                'Sinyal RSSI' => ($lastReading?->signal_strength ?? $d->rssi ?? -75).' dBm',
                'Firmware' => $d->firmware_version ?? '1.0.0',
                'Pembacaan Terakhir' => $lastReading
                    ? Carbon::parse($lastReading->reading_time)->format('d/m/Y H:i:s')
                    : 'Belum ada data',
            ];
        });

        return $this->respondWithFormat($mapped, $format, 'maintenance', $devices->count());
    }

    // Export log aktivitas
    private function exportSystemLogs(Request $request, string $format)
    {
        $query = Activity::with('causer')->orderBy('created_at', 'asc');

        if ($bounds = $this->dateRangeBounds($request)) {
            $query->whereBetween('created_at', $bounds);
        }

        $logs = $query->get();

        $mapped = $logs->map(fn ($l) => [
            'ID Log' => 'LOG-'.str_pad($l->id, 3, '0', STR_PAD_LEFT),
            'Stempel Waktu' => $l->created_at->format('d/m/Y H:i:s'),
            'Pengguna' => $l->causer?->name ?? 'Sistem',
            'Aktivitas' => $l->description,
            'Modul Sistem' => $l->log_name ?? 'Sistem',
            'Status' => 'Berhasil',
            'Alamat IP' => $l->properties['ip'] ?? '127.0.0.1',
        ]);

        return $this->respondWithFormat($mapped, $format, 'system-logs', $logs->count());
    }

    // Query readings dengan filter
    private function getFilteredReadings(Request $request)
    {
        $type = $request->get('type', 'raw-data');
        $relations = $type === 'analysis'
            ? ['device.garden.plant', 'device.garden.komoditi', 'device.landPlot']
            : ['device'];

        $query = IotReading::with($relations)
            ->orderBy('reading_time', 'desc');

        if ($bounds = $this->dateRangeBounds($request)) {
            $query->where(function ($q) use ($bounds) {
                $q->whereBetween('reading_time', $bounds)
                  ->orWhereBetween('created_at', $bounds);
            });
        }
        if ($request->filled('device_id')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('device', fn ($dq) => $dq->where('device_code', $request->device_id))
                  ->orWhere('device_id', $request->device_id);
            });
        }

        // Limit maks 25.000 baris agar cepat dan hemat RAM
        return $query->limit(25000)->get();
    }

    private function dateRangeBounds(Request $request): ?array
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : null;
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : null;

        if (! $startDate && ! $endDate) {
            return null;
        }

        $startDate ??= Carbon::create(1970, 1, 1)->startOfDay();
        $endDate ??= now()->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        return [$startDate, $endDate];
    }

    // Response CSV atau JSON
    private function respondWithFormat($mapped, string $format, string $type, int $totalCount)
    {
        if ($format === 'csv') {
            return $this->streamCsv($mapped, $type);
        }

        return response()->json([
            'status' => 'success',
            'type' => $type,
            'count' => $totalCount,
            'data' => $mapped->toArray(),
        ]);
    }

    // Stream CSV download
    private function streamCsv($mapped, string $type)
    {
        $filename = 'agrisense_'.$type.'_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($mapped) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            $first = $mapped->first();
            if ($first) {
                fputcsv($file, array_keys(is_array($first) ? $first : $first->toArray()));
            }

            foreach ($mapped as $row) {
                fputcsv($file, is_array($row) ? array_values($row) : array_values($row->toArray()));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
