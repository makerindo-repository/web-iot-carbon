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

        $mapped = $data->map(fn ($r) => [
            'Device ID' => $r->device->device_code ?? '',
            'Jenis Tanaman' => $r->device->garden?->komoditi?->nama_komoditi ?? $r->device->garden?->plant?->name ?? $r->device->garden?->plant_types ?? 'N/A',
            'Waktu' => Carbon::parse($r->reading_time)->format('d/m/Y H:i:s'),
            'CO2 (ppm)' => round($r->co2_sensor, 2),
            'TVOC (ppb)' => round($r->tvoc_ppb ?? 0, 0),
            'Suhu Udara (°C)' => round($r->air_temperature_sensor, 1),
            'Kelembapan Udara (%)' => round($r->air_humidity_sensor, 1),
            'Tekanan (hPa)' => round($r->air_pressure_hpa ?? 0, 1),
            'Cahaya (Lux)' => round($r->light_lux ?? 0, 0),
            'Kelembapan Tanah (%)' => round($r->soil_moisture, 1),
            'Suhu Tanah (°C)' => round($r->soil_temperature ?? 0, 1),
            'pH Tanah' => round($r->soil_ph, 2),
            'N (mg/kg)' => round($r->soil_n_mg_kg ?? 0, 0),
            'P (mg/kg)' => round($r->soil_p_mg_kg ?? 0, 0),
            'K (mg/kg)' => round($r->soil_k_mg_kg ?? 0, 0),
            'Baterai (%)' => $r->battery_percent ?? 0,
        ]);

        return $this->respondWithFormat($mapped, $format, 'raw-data', $data->count());
    }

    // Export agregasi per device
    private function exportAnalysis(Request $request, string $format)
    {
        $data = $this->getFilteredReadings($request);

        // Group by device and calculate aggregates
        $grouped = $data->groupBy(fn ($r) => $r->device->device_code ?? 'UNKNOWN');

        $mapped = $grouped->map(function ($readings, $deviceCode) {
            $device = $readings->first()->device;
            $plantName = $device->garden?->komoditi?->nama_komoditi ?? $device->garden?->plant?->name ?? 'N/A';
            $count = $readings->count();

            return [
                'Device ID' => $deviceCode,
                'Jenis Tanaman' => $plantName,
                'Jumlah Pembacaan' => $count,
                'Periode Awal' => Carbon::parse($readings->min('reading_time'))->format('d/m/Y H:i'),
                'Periode Akhir' => Carbon::parse($readings->max('reading_time'))->format('d/m/Y H:i'),
                'Rata-rata CO2 (ppm)' => round($readings->avg('co2_sensor'), 1),
                'Min CO2' => round($readings->min('co2_sensor'), 1),
                'Max CO2' => round($readings->max('co2_sensor'), 1),
                'Rata-rata Suhu (°C)' => round($readings->avg('air_temperature_sensor'), 1),
                'Min Suhu' => round($readings->min('air_temperature_sensor'), 1),
                'Max Suhu' => round($readings->max('air_temperature_sensor'), 1),
                'Rata-rata Kelembapan Tanah (%)' => round($readings->avg('soil_moisture'), 1),
                'Rata-rata pH' => round($readings->avg('soil_ph'), 2),
                'Rata-rata Cahaya (Lux)' => round($readings->avg('light_lux'), 0),
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

        // Reading terakhir per device dalam SATU query (sebelumnya N+1: 1 query
        // per device). MAX(id) per device_id (id monoton mengikuti reading_time)
        // dalam rentang tanggal, lalu ambil baris-nya sekali jalan & key-by device.
        $bounds = $this->dateRangeBounds($request);
        $latestIds = IotReading::selectRaw('MAX(id) as id')
            ->whereIn('device_id', $devices->pluck('id'))
            ->when($bounds, fn ($query) => $query->whereBetween('reading_time', $bounds))
            ->groupBy('device_id')
            ->pluck('id');
        $latestReadings = IotReading::whereIn('id', $latestIds)->get()->keyBy('device_id');

        $mapped = $devices->map(function ($d) use ($latestReadings) {
            $lastReading = $latestReadings->get($d->id);

            return [
                'Device ID' => $d->device_code,
                'Nama Perangkat' => $d->name ?? $d->device_code,
                'Jenis Tanaman' => $d->garden?->komoditi?->nama_komoditi ?? $d->garden?->plant?->name ?? 'N/A',
                'Lahan' => $d->landPlot?->plot_name ?? 'N/A',
                'Status' => $d->status ?? 'unknown',
                'Baterai (%)' => $lastReading?->battery_percent ?? 'N/A',
                'RSSI (dBm)' => $lastReading?->signal_strength ?? 'N/A',
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
            'Log ID' => 'LOG-'.str_pad($l->id, 3, '0', STR_PAD_LEFT),
            'Waktu' => $l->created_at->format('d/m/Y H:i:s'),
            'User' => $l->causer?->name ?? 'System',
            'Modul' => $l->log_name ?? 'System',
            'Aktivitas' => $l->description,
        ]);

        return $this->respondWithFormat($mapped, $format, 'system-logs', $logs->count());
    }

    // Query readings dengan filter
    private function getFilteredReadings(Request $request)
    {
        $query = IotReading::with(['device.garden.plant', 'device.garden.komoditi', 'device.landPlot'])
            ->orderBy('reading_time', 'asc');

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
