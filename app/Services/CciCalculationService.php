<?php

namespace App\Services;

use App\Models\CciAnalytic;
use App\Models\IotReading;

class CciCalculationService
{
    const WEIGHTS = [
        'co2'      => 0.25,
        'soc'      => 0.20,
        'moisture' => 0.15,
        'ph'       => 0.15,
        'temp'     => 0.10,
        'npk'      => 0.15,
    ];

    const RANGES = [
        'co2'      => ['min' => 300, 'max' => 2000],
        'soc'      => ['min' => 0,   'max' => 100],
        'moisture' => ['min' => 0,   'max' => 100],
        'ph'       => ['min' => 3,   'max' => 10],
        'temp'     => ['min' => -10, 'max' => 50],
        'n'        => ['min' => 0,   'max' => 300],
        'p'        => ['min' => 0,   'max' => 200],
        'k'        => ['min' => 0,   'max' => 500],
    ];

    public static function calculate(IotReading $reading): array
    {
        $co2Norm = self::normalize($reading->co2_sensor ?? 0, 'co2');
        $co2Score = 1 - $co2Norm;
        $socNorm = self::normalize($reading->soil_organic_carbon ?? 0, 'soc');
        $moistureNorm = self::normalize($reading->soil_moisture ?? 0, 'moisture');
        
        $phValue = $reading->soil_ph ?? 7.0;
        $phScore = max(0, min(1, 1 - abs($phValue - 6.75) / 3.25));

        $tempValue = $reading->air_temperature_sensor ?? 25;
        $tempScore = max(0, min(1, 1 - abs($tempValue - 25) / 25));

        $nNorm = self::normalize($reading->soil_n_mg_kg ?? 0, 'n');
        $pNorm = self::normalize($reading->soil_p_mg_kg ?? 0, 'p');
        $kNorm = self::normalize($reading->soil_k_mg_kg ?? 0, 'k');
        $npkScore = ($nNorm + $pNorm + $kNorm) / 3;

        $cci = (self::WEIGHTS['co2'] * $co2Score)
             + (self::WEIGHTS['soc'] * $socNorm)
             + (self::WEIGHTS['moisture'] * $moistureNorm)
             + (self::WEIGHTS['ph'] * $phScore)
             + (self::WEIGHTS['temp'] * $tempScore)
             + (self::WEIGHTS['npk'] * $npkScore);

        $cci = max(0, min(1, $cci));

        return [
            'cci_value'  => round($cci, 3),
            'cci_status' => self::classify($cci),
            'breakdown'  => [
                'co2_score'      => round($co2Score, 3),
                'soc_score'      => round($socNorm, 3),
                'moisture_score' => round($moistureNorm, 3),
                'ph_score'       => round($phScore, 3),
                'temp_score'     => round($tempScore, 3),
                'npk_score'      => round($npkScore, 3),
            ]
        ];
    }

    public static function calculateAndStore(IotReading $reading): CciAnalytic
    {
        $result = self::calculate($reading);
        return CciAnalytic::create([
            'iot_reading_id' => $reading->id,
            'device_id'      => $reading->device->device_code ?? 'UNKNOWN',
            'cci_value'      => $result['cci_value'],
            'cci_status'     => $result['cci_status'],
            'model_version'  => '1.0.0',
            'notes'          => json_encode($result['breakdown']),
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
        if ($max === $min) return 0;
        return max(0, min(1, ($value - $min) / ($max - $min)));
    }

    private static function classify(float $cci): string
    {
        if ($cci < 0.25) return 'rendah';
        if ($cci < 0.50) return 'sedang';
        if ($cci < 0.75) return 'tinggi';
        return 'kritis';
    }
}
