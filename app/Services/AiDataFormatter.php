<?php

namespace App\Services;

use App\Models\CarbonDailyStock;
use App\Models\IotReading;
use Carbon\Carbon;

/**
 * AiDataFormatter
 *
 * Mengambil data sensor mentah dari tabel iot_readings,
 * lalu membentuknya menjadi JSON yang sesuai dengan input_contract.json
 * dan feature engineering pipeline yang digunakan saat training model.
 *
 * Fitur yang dihitung:
 * - sin_hour / cos_hour  : Time embedding berdasarkan jam
 * - sin_dow / cos_dow    : Time embedding berdasarkan hari dalam seminggu
 * - is_night             : Flag apakah malam hari (jam 19-06)
 * - vpd_approx           : Estimasi Vapour Pressure Deficit
 * - npk_total            : Jumlah N + P + K
 * - npk_balance          : Rasio N terhadap total NPK
 * - ph_sm_interact       : Interaksi pH × Soil Moisture
 */
class AiDataFormatter
{
    /**
     * Kolom sensor yang dipetakan dari DB ke nama kolom model Python.
     * Key = nama kolom di Python model, Value = nama kolom di iot_readings DB.
     */
    private const COLUMN_MAP = [
        'CO2 (ppm)' => 'co2_sensor',
        'TVOC (ppb)' => 'tvoc_ppb',
        'Suhu Udara (°C)' => 'air_temperature_sensor',
        'Kelembapan Udara (%)' => 'air_humidity_sensor',
        'Tekanan (hPa)' => 'air_pressure_hpa',
        'Cahaya (Lux)' => 'light_lux',
        'Kelembapan Tanah (%)' => 'soil_moisture',
        'Suhu Tanah (°C)' => 'soil_temperature',
        'pH Tanah' => 'soil_ph',
        'N (mg/kg)' => 'soil_n_mg_kg',
        'P (mg/kg)' => 'soil_p_mg_kg',
        'K (mg/kg)' => 'soil_k_mg_kg',
        'Baterai (%)' => 'battery_percent',
        'RSSI (dBm)' => 'signal_strength',
    ];

    /**
     * Siapkan data input untuk model forecasting dari data sensor terbaru.
     *
     * @param  int  $deviceId  ID device di database
     * @param  string  $deviceCode  Kode device (contoh: AGRISENSE-CC-001)
     * @param  int  $rowCount  Jumlah baris terakhir yang diambil (min 24 untuk LSTM seq_len)
     * @return array|null Array data yang siap dikirim ke Python, atau null jika data tidak cukup
     */
    public static function prepareForDevice(int $deviceId, string $deviceCode, int $rowCount = 30): ?array
    {
        $readings = IotReading::with(['device.garden.plant', 'device.landPlot', 'landPlot'])
            ->where('device_id', $deviceId)
            ->where('data_valid', true)
            ->orderBy('reading_time', 'desc')
            ->limit($rowCount)
            ->get()
            ->reverse()  // Urutkan dari terlama ke terbaru
            ->values();

        if ($readings->count() < 5) {
            return null; // Data tidak cukup untuk prediksi
        }

        $rows = [];
        $firstTimestamp = Carbon::parse($readings->first()->reading_time);
        $latestFeatureRow = [];

        foreach ($readings as $reading) {
            $timestamp = Carbon::parse($reading->reading_time);
            $hour = $timestamp->hour + ($timestamp->minute / 60.0);
            $dow = $timestamp->dayOfWeek; // 0=Sunday, 6=Saturday

            // Sensor values dari database
            $row = [
                'Device ID' => $deviceCode,
                'Timestamp' => $timestamp->toIso8601String(),
            ];

            // Map kolom sensor
            foreach (self::COLUMN_MAP as $pythonName => $dbColumn) {
                $row[$pythonName] = (float) ($reading->{$dbColumn} ?? 0);
            }

            // Feature Engineering (harus identik dengan pipeline training)
            $row['sin_hour'] = sin(2 * M_PI * $hour / 24);
            $row['cos_hour'] = cos(2 * M_PI * $hour / 24);
            $row['sin_dow'] = sin(2 * M_PI * $dow / 7);
            $row['cos_dow'] = cos(2 * M_PI * $dow / 7);
            $row['is_night'] = ($timestamp->hour >= 19 || $timestamp->hour < 6) ? 1 : 0;

            // VPD Approximation (Tetens Formula)
            $temp = (float) ($reading->air_temperature_sensor ?? 25);
            $humidity = (float) ($reading->air_humidity_sensor ?? 60);
            $es = 0.6108 * exp((17.27 * $temp) / ($temp + 237.3)); // kPa
            $ea = $es * ($humidity / 100);
            $row['vpd_approx'] = round($es - $ea, 4);

            // NPK Features
            $n = (float) ($reading->soil_n_mg_kg ?? 0);
            $p = (float) ($reading->soil_p_mg_kg ?? 0);
            $k = (float) ($reading->soil_k_mg_kg ?? 0);
            $npkTotal = $n + $p + $k;
            $row['npk_total'] = round($npkTotal, 2);
            $row['npk_balance'] = $npkTotal > 0 ? round($n / $npkTotal, 4) : 0;

            // Interaction Feature
            $ph = (float) ($reading->soil_ph ?? 7);
            $sm = (float) ($reading->soil_moisture ?? 50);
            $row['ph_sm_interact'] = round($ph * $sm, 2);

            // Fitur model terbaru sintetik_90. Ini mempertahankan sensor mentah
            // sekaligus menambahkan indikator karbon ilmiah dari CarbonFluxService.
            $latestFeatureRow = self::buildSintetik90Features($reading, $timestamp, $firstTimestamp);
            $row = array_merge($row, $latestFeatureRow);

            $rows[] = $row;
        }

        $latestReading = $readings->last();

        return [
            'device_id' => $deviceCode,
            'device_db_id' => $deviceId,
            'row_count' => count($rows),
            'latest_reading_time' => $latestReading->reading_time->toIso8601String(),
            'current_values' => [
                'CO2 (ppm)' => (float) ($latestReading->co2_sensor ?? 0),
                'Carbon Flux (NEE AgriSense)' => (float) ($latestReading->carbon_flux ?? (($latestFeatureRow['gpp'] ?? 0) - ($latestFeatureRow['reco'] ?? 0))),
                'Carbon Potential Score' => CarbonFluxService::calculateCPS(
                    (float) ($latestFeatureRow['c_current'] ?? CarbonFluxService::DEFAULT_SOC_BASELINE_GC_M2),
                    (float) ($latestFeatureRow['c_max'] ?? CarbonFluxService::estimateCMax(CarbonFluxService::DEFAULT_SOC_BASELINE_GC_M2))
                ),
                'Soil Moisture (%)' => (float) ($latestReading->soil_moisture ?? 0),
                'pH Tanah' => (float) ($latestReading->soil_ph ?? 0),
                'soil_moisture' => (float) ($latestReading->soil_moisture ?? 0),
                'soil_ph' => (float) ($latestReading->soil_ph ?? 0),
            ],
            'rows' => $rows,
        ];
    }

    private static function buildSintetik90Features(IotReading $reading, Carbon $timestamp, Carbon $firstTimestamp): array
    {
        $carbon = CarbonFluxService::calculate($reading);
        $breakdown = $carbon['breakdown'] ?? [];

        $socBaseline = (float) (
            $reading->landPlot?->soc_baseline_gc_m2
            ?? $reading->device?->landPlot?->soc_baseline_gc_m2
            ?? 0
        );
        if ($socBaseline <= 0) {
            $socBaseline = CarbonFluxService::DEFAULT_SOC_BASELINE_GC_M2;
        }

        $configuredCMax = (float) (
            $reading->landPlot?->c_max_gc_m2
            ?? $reading->device?->landPlot?->c_max_gc_m2
            ?? 0
        );
        $cMax = $configuredCMax > 0
            ? $configuredCMax
            : CarbonFluxService::estimateCMax($socBaseline);

        $biomassAcc = (float) (CarbonDailyStock::where('device_id', $reading->device_id)
            ->whereDate('stock_date', '<=', $timestamp->toDateString())
            ->orderByDesc('stock_date')
            ->value('cumulative_npp_gc_m2') ?? 0);

        $hour = $timestamp->hour + ($timestamp->minute / 60.0);
        $dow = $timestamp->dayOfWeek;
        $plantType = strtolower((string) ($breakdown['plant_type'] ?? 'default'));
        $cCurrent = $socBaseline + $biomassAcc;

        // Hanya field yang benar-benar dikirim node Carbon di lapangan
        // (tidak ada sensor tanah fisik — lihat CarbonFluxService::calculate()).
        // Mensyaratkan soil_* di sini membuat has_full_data selalu 0 walau
        // data atmosfer/karbon yang dipakai target aktif (CO2, Carbon Flux,
        // Carbon Potential Score) lengkap dan valid.
        $required = [
            $reading->co2_sensor,
            $reading->air_temperature_sensor,
            $reading->air_humidity_sensor,
            $reading->light_lux,
        ];
        $hasFullData = collect($required)->every(fn ($value) => is_numeric($value) && (float) $value > 0);

        return [
            'suhu_udara' => (float) ($reading->air_temperature_sensor ?? 0),
            'kelembapan_udara' => (float) ($reading->air_humidity_sensor ?? 0),
            'tekanan_hpa' => (float) ($reading->air_pressure_hpa ?? 0),
            'cahaya_lux' => (float) ($reading->light_lux ?? 0),
            'kelembapan_tanah' => (float) ($reading->soil_moisture ?? 0),
            'suhu_tanah' => (float) ($reading->soil_temperature ?? 0),
            'ph_tanah' => (float) ($reading->soil_ph ?? 0),
            'tvoc_ppb' => (float) ($reading->tvoc_ppb ?? 0),
            'n_mg_kg' => (float) ($reading->soil_n_mg_kg ?? 0),
            'p_mg_kg' => (float) ($reading->soil_p_mg_kg ?? 0),
            'k_mg_kg' => (float) ($reading->soil_k_mg_kg ?? 0),
            'epsilon' => (float) ($breakdown['epsilon_max'] ?? CarbonFluxService::getEpsilon('default')),
            'fapar' => (float) ($breakdown['fAPAR'] ?? CarbonFluxService::getFapar('default')),
            'par' => (float) ($breakdown['par_umol'] ?? ((float) ($reading->light_lux ?? 0) * CarbonFluxService::LUX_TO_PAR)),
            't_scalar' => (float) ($breakdown['t_scalar'] ?? 0),
            'w_scalar' => (float) ($breakdown['w_scalar'] ?? 0),
            'c_scalar' => (float) ($breakdown['c_scalar'] ?? 0),
            'gpp' => (float) ($carbon['gpp'] ?? 0),
            'reco' => (float) ($carbon['reco'] ?? 0),
            'npp' => (float) ($carbon['npp'] ?? 0),
            'soc_baseline_gC_m2' => $socBaseline,
            'c_biomass_acc' => $biomassAcc,
            'c_current' => $cCurrent,
            'c_max' => $cMax,
            'elapsed_hours' => max(0, $firstTimestamp->diffInMinutes($timestamp) / 60),
            'hour_sin' => sin(2 * M_PI * $hour / 24),
            'hour_cos' => cos(2 * M_PI * $hour / 24),
            'dow_sin' => sin(2 * M_PI * $dow / 7),
            'dow_cos' => cos(2 * M_PI * $dow / 7),
            'is_daytime' => ($timestamp->hour >= 6 && $timestamp->hour < 18) ? 1 : 0,
            'is_cabai' => (str_contains($plantType, 'cabai') || str_contains($plantType, 'pepper')) ? 1 : 0,
            'has_full_data' => $hasFullData ? 1 : 0,
        ];
    }
}
