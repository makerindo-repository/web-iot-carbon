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
        $data = $this->getFilteredReadings($request);

        $mapped = $data->map(function ($r) {
            $rawDir = $r->wind_direction_deg ?? $r->wind_direction ?? 0;
            $windDirText = 'Utara (N)';
            if (is_numeric($rawDir)) {
                $dirs = ['Utara (N)', 'Timur Laut (NE)', 'Timur (E)', 'Tenggara (SE)', 'Selatan (S)', 'Barat Daya (SW)', 'Barat (W)', 'Barat Laut (NW)'];
                $idx = (int) round($rawDir / 45) % 8;
                $windDirText = $dirs[$idx] ?? 'Utara (N)';
            } elseif (is_string($rawDir) && trim($rawDir)) {
                $windDirText = $rawDir;
            }

            $batPercent = $r->battery_percent ?? 85;
            $batVolt = round($r->battery_voltage ?? 12.4, 2);

            return [
                'Waktu Telemetry' => Carbon::parse($r->reading_time)->format('d/m/Y H:i:s'),
                'ID Perangkat' => $r->device?->id ?? $r->device_id ?? '1',
                'Kode RH Perangkat' => $r->device?->device_code ?? 'AGRISENSE-CC-001',
                'Nama Perangkat' => $r->device?->name ?? $r->device?->device_code ?? 'NODE AGRISENSE',
                'Kecepatan Angin (km/h)' => round($r->wind_speed_kmh ?? 0, 1),
                'Arah Angin' => $windDirText,
                'Latitude' => round($r->device?->latitude ?? $r->latitude ?? -6.830000, 6),
                'Longitude' => round($r->device?->longitude ?? $r->longitude ?? 107.910000, 6),
                'Elevasi (MDPL)' => round($r->altitude_m ?: ($r->device?->altitude ?: 720), 0),
                'Baterai & Tegangan' => "{$batPercent}% ({$batVolt}V)",
                'CO2 (ppm)' => round($r->co2_sensor ?? 0, 1),
                'CH4 (ppm)' => round($r->ch4_ppm ?? 0, 1),
                'NO2 (ppb)' => round($r->no2_ppb ?? 0, 1),
                'Suhu Udara (°C)' => round($r->air_temperature_sensor ?? 0, 1),
                'Kelembapan Udara (%)' => round($r->air_humidity_sensor ?? 0, 1),
            ];
        });

        return $this->respondWithFormat($mapped, $format, 'raw-data', $data->count());
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
                'Rata-rata NO2 (ppb)' => round($readings->avg('no2_ppb'), 1),
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
        $query = IotReading::with(['device.garden.plant', 'device.garden.komoditi', 'device.landPlot'])
            ->orderBy('reading_time', 'desc');

        if ($bounds = $this->dateRangeBounds($request)) {
            $query->whereBetween('reading_time', $bounds);
        }
        if ($request->filled('device_id')) {
            $query->whereHas('device', fn ($q) => $q->where('device_code', $request->device_id));
        }

        // Limit maks 10.000 baris
        return $query->limit(10000)->get();
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
