<?php

namespace App\Services;

use App\Models\CciAnalytic;
use App\Models\IotReading;
use Illuminate\Support\Facades\Log;

class CciCalculationService
{
    /**
     * Baseline CO₂ atmosfer global (2024, NOAA Mauna Loa).
     */
    const CO2_BASELINE = 420.0;

    const WEIGHTS = [
        'co2' => 0.25,
        'soc' => 0.20,
        'moisture' => 0.15,
        'ph' => 0.15,
        'temp' => 0.10,
        'npk' => 0.15,
    ];

    const RANGES = [
        'co2' => ['min' => 300, 'max' => 2000],
        'soc' => ['min' => 0,   'max' => 100],
        'moisture' => ['min' => 0,   'max' => 100],
        'ph' => ['min' => 3,   'max' => 10],
        'temp' => ['min' => -10, 'max' => 50],
        'n' => ['min' => 0,   'max' => 300],
        'p' => ['min' => 0,   'max' => 200],
        'k' => ['min' => 0,   'max' => 500],
    ];

    public static function calculate(IotReading $reading): array
    {
        // ───────────────────────────────────────────────────────
        // CO₂ Score — Indikator Aktivitas Biologis Tanah
        // ───────────────────────────────────────────────────────
        // CO₂ TINGGI = respirasi mikroba AKTIF = carbon cycling POSITIF.
        // Jangan membalik skor! (bukan 1 - co2_norm)
        // Anomali hanya jika >2x baseline (gangguan: pengolahan tanah, dsb).
        // Ref: Ryan & Law (2005), Biogeochemistry
        $co2Ppm = $reading->co2_sensor ?? self::CO2_BASELINE;
        $co2Ratio = $co2Ppm / self::CO2_BASELINE;
        if ($co2Ratio >= 0.9 && $co2Ratio <= 1.4) {
            $co2Score = min(1.0, 0.5 + $co2Ratio * 0.35);
        } elseif ($co2Ratio > 1.4) {
            $co2Score = max(0.3, 1.0 - ($co2Ratio - 1.4) * 0.7);
        } else {
            $co2Score = max(0.2, $co2Ratio * 0.6);
        }

        // ───────────────────────────────────────────────────────
        // SOC Score — Soil Organic Carbon proxy
        // ───────────────────────────────────────────────────────
        $socNorm = self::normalize($reading->soil_organic_carbon ?? 0, 'soc');

        // ───────────────────────────────────────────────────────
        // Moisture Score — Fungsi Optimality Trapezoid
        // ───────────────────────────────────────────────────────
        // Rentang optimal: 40-60% (kapasitas lapang).
        // Ref: FAO Irrigation & Drainage Paper No. 56
        $moistureVal = $reading->soil_moisture ?? 0;
        if ($moistureVal >= 40 && $moistureVal <= 60) {
            $moistureScore = 1.0;
        } elseif ($moistureVal < 40) {
            $moistureScore = max(0.0, $moistureVal / 40);
        } else {
            $moistureScore = max(0.0, 1 - ($moistureVal - 60) / 40);
        }

        // ───────────────────────────────────────────────────────
        // pH Score — Optimality terhadap 6.5 (tanaman tropis)
        // ───────────────────────────────────────────────────────
        $phValue = $reading->soil_ph ?? 7.0;
        $phScore = max(0, min(1, 1 - abs($phValue - 6.5) / 3.5));

        // ───────────────────────────────────────────────────────
        // Temperature Score — Kurva Parabola Aktivitas Mikroba
        // ───────────────────────────────────────────────────────
        // T_opt=25°C, T_min=5°C, T_max=45°C
        // Ref: Lloyd & Taylor (1994)
        $tempValue = $reading->air_temperature_sensor ?? 25;
        if ($tempValue <= 5 || $tempValue >= 45) {
            $tempScore = 0.0;
        } else {
            $num = ($tempValue - 5) * (45 - $tempValue);
            $den = (25 - 5) * (45 - 25);
            $tempScore = max(0.0, min(1.0, $num / $den));
        }

        // ───────────────────────────────────────────────────────
        // NPK Score — Rata-rata normalisasi ketersediaan hara
        // ───────────────────────────────────────────────────────
        $nNorm = self::normalize($reading->soil_n_mg_kg ?? 0, 'n');
        $pNorm = self::normalize($reading->soil_p_mg_kg ?? 0, 'p');
        $kNorm = self::normalize($reading->soil_k_mg_kg ?? 0, 'k');
        $npkScore = ($nNorm + $pNorm + $kNorm) / 3;

        // ───────────────────────────────────────────────────────
        // CCI = Weighted Composite Score
        // ───────────────────────────────────────────────────────
        $cci = (self::WEIGHTS['co2'] * $co2Score)
             + (self::WEIGHTS['soc'] * $socNorm)
             + (self::WEIGHTS['moisture'] * $moistureScore)
             + (self::WEIGHTS['ph'] * $phScore)
             + (self::WEIGHTS['temp'] * $tempScore)
             + (self::WEIGHTS['npk'] * $npkScore);

        $cci = max(0, min(1, $cci));

        return [
            'cci_value' => round($cci, 3),
            'cci_status' => self::classify($cci),
            'type' => 'proxy',
            'note' => 'Composite Environmental Score. For scientific carbon analysis, see CPS.',
            'breakdown' => [
                'co2_score' => round($co2Score, 3),
                'soc_score' => round($socNorm, 3),
                'moisture_score' => round($moistureScore, 3),
                'ph_score' => round($phScore, 3),
                'temp_score' => round($tempScore, 3),
                'npk_score' => round($npkScore, 3),
            ],
        ];
    }

    public static function calculateAndStore(IotReading $reading): ?CciAnalytic
    {
        // Plausibility soft-skip — kalau ada field di luar rentang fisis,
        // skip kalkulasi dan return null (TIDAK throw, reading tetap simpan
        // untuk audit). Caller (IotReadingController) sudah handle null.
        $checks = [
            'soil_ph' => [3, 10],
            'soil_moisture' => [0, 100],
            'air_humidity_sensor' => [0, 100],
            'air_temperature_sensor' => [-20, 60],
            'co2_sensor' => [200, 50000],
        ];
        foreach ($checks as $field => [$min, $max]) {
            $value = $reading->{$field};
            if ($value === null) {
                continue;
            }
            if ($value < $min || $value > $max) {
                Log::info('CCI plausibility skip', [
                    'reading_id' => $reading->id,
                    'field' => $field,
                    'value' => $value,
                    'expected' => "between {$min} and {$max}",
                ]);

                return null;
            }
        }

        $result = self::calculate($reading);

        return CciAnalytic::create([
            'iot_reading_id' => $reading->id,
            'device_id' => $reading->device->device_code ?? 'UNKNOWN',
            'cci_value' => $result['cci_value'],
            'cci_status' => $result['cci_status'],
            'model_version' => '1.1.0-scientific',
            'notes' => json_encode($result['breakdown']),
        ]);
    }

    public static function batchCalculate(int $limit = 100): int
    {
        $readings = IotReading::whereDoesntHave('cciAnalytic')
            ->with('device')->orderBy('reading_time', 'desc')
            ->limit($limit)->get();
        $count = 0;
        foreach ($readings as $reading) {
            self::calculateAndStore($reading);
            $count++;
        }

        return $count;
    }

    private static function normalize(float $value, string $param): float
    {
        $min = self::RANGES[$param]['min'];
        $max = self::RANGES[$param]['max'];
        if ($max === $min) {
            return 0;
        }

        return max(0, min(1, ($value - $min) / ($max - $min)));
    }

    private static function classify(float $cci): string
    {
        if ($cci < 0.25) {
            return 'rendah';
        }
        if ($cci < 0.50) {
            return 'sedang';
        }
        if ($cci < 0.75) {
            return 'tinggi';
        }

        return 'sangat_tinggi';
    }
}
