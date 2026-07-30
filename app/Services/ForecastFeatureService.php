<?php

namespace App\Services;

use App\Models\Device;
use App\Models\IotReading;
use Carbon\Carbon;

/**
 * Membangun 36 fitur input model forecasting (sintetik_90) secara deterministik
 * dari data sensor AgriSense yang riil. Sebagian besar fitur dihitung langsung
 * dari pembacaan sensor atau rumus fisis/meteorologis baku (lihat komentar
 * masing-masing method). Empat konstanta TIDAK memiliki padanan pengukuran
 * langsung pada sensor AgriSense saat ini (karbon organik tanah/SOC) dan
 * karena itu memakai ASUMSI TETAP yang didokumentasikan secara rinci pada
 * docs/deploy_model_bundle/FORECAST_LIVE_ASSUMPTIONS.md — JANGAN mengubah
 * nilai-nilai tsb di sini tanpa memperbarui dokumen tersebut.
 */
class ForecastFeatureService
{
    public const ASSUMPTIONS_VERSION = 'v1-fixed-baseline-2026-07-30';

    // ── Asumsi tetap (lihat FORECAST_LIVE_ASSUMPTIONS.md) ──
    private const SOC_BASELINE_GC_M2 = 6000.0; // titik tengah rentang pelatihan 3000-10000
    private const C_MAX_RATIO = 1.375; // titik tengah rentang pelatihan 1.30-1.45
    private const AMBIENT_CO2_BASELINE_PPM = 415.0; // baseline CO2 atmosferik regional (bukan nilai sensor per-node)
    private const CO2_REFERENCE_PPM = 420.0; // identik dengan CO2_BASELINE pada feature_engineering_fluxnet.py

    // ── Konstanta rumus fisis, identik dengan feature_engineering_fluxnet.py ──
    private const LUX_TO_PAR = 0.0185;
    private const PAR_TO_MJ_PER_HOUR = 0.00756;
    private const T_MIN = 10.0;
    private const T_OPT = 28.0;
    private const T_MAX = 40.0;
    private const FIELD_CAPACITY = 60.0;
    private const AUTOTROPHIC_RESP_FRACTION = 0.53;
    private const R_REF = 2.0;
    private const T_REF_K = 283.15;
    private const T0_K = 227.13;
    private const E0 = 308.56;

    public static function buildFeatureVector(Device $device, IotReading $reading): array
    {
        $temp = (float) ($reading->air_temperature_sensor ?? 25.0);
        $humidity = (float) ($reading->air_humidity_sensor ?? 70.0);
        $soilMoisture = (float) ($reading->soil_moisture ?? 40.0);
        $lightLux = (float) ($reading->light_lux ?? 0.0);

        $epsilon = self::clip(1.20 * self::clip(1 - 0.02 * abs($temp - 28.0), 0.7, 1.0), 0.60, 1.80);
        $fapar = self::clip(0.55 + 0.004 * ($soilMoisture - 20.0), 0.30, 0.90);
        $par = $lightLux * self::LUX_TO_PAR;
        $tScalar = self::calcTScalar($temp);
        $wScalar = self::clip($soilMoisture / self::FIELD_CAPACITY, 0.0, 1.0);
        $cScalar = self::AMBIENT_CO2_BASELINE_PPM / self::CO2_REFERENCE_PPM;

        $parMj = $par * self::PAR_TO_MJ_PER_HOUR * 0.5;
        $gpp = max(0.0, $parMj * $fapar * $epsilon * $tScalar * $wScalar * $cScalar);
        $reco = self::calcReco($temp) * 0.0036 * 12 * 0.5;
        $npp = $gpp * (1 - self::AUTOTROPHIC_RESP_FRACTION);

        $socBaseline = self::SOC_BASELINE_GC_M2;
        $cBiomassAcc = 0.0; // disederhanakan menjadi kondisi baseline — lihat dokumentasi asumsi
        $cCurrent = $socBaseline + $cBiomassAcc;
        $cMax = $socBaseline * self::C_MAX_RATIO;

        $readingTime = Carbon::parse($reading->reading_time);
        $hour = $readingTime->hour + $readingTime->minute / 60.0;
        $dow = (int) $readingTime->dayOfWeek;

        [$tempLag1, $moistureLag1, $co2Lag1] = self::resolveLagValues(
            $device, $reading, $temp, $soilMoisture, (float) ($reading->co2_sensor ?? 400.0)
        );
        [$tempRoll6, $moistureRoll6] = self::resolveRollingAverages($device, $reading, $temp, $soilMoisture);

        return [
            'suhu_udara' => $temp,
            'kelembapan_udara' => $humidity,
            'tekanan_hpa' => (float) ($reading->air_pressure_hpa ?? 1010.0),
            'cahaya_lux' => $lightLux,
            'kelembapan_tanah' => $soilMoisture,
            'suhu_tanah' => (float) ($reading->soil_temperature ?? $temp),
            'ph_tanah' => (float) ($reading->soil_ph ?? 6.5),
            'tvoc_ppb' => (float) ($reading->tvoc_ppb ?? 0.0),
            'n_mg_kg' => (float) ($reading->soil_n_mg_kg ?? 0.0),
            'p_mg_kg' => (float) ($reading->soil_p_mg_kg ?? 0.0),
            'k_mg_kg' => (float) ($reading->soil_k_mg_kg ?? 0.0),
            'epsilon' => $epsilon,
            'fapar' => $fapar,
            'par' => $par,
            't_scalar' => $tScalar,
            'w_scalar' => $wScalar,
            'c_scalar' => $cScalar,
            'gpp' => $gpp,
            'reco' => $reco,
            'npp' => $npp,
            'soc_baseline_gC_m2' => $socBaseline,
            'c_biomass_acc' => $cBiomassAcc,
            'c_current' => $cCurrent,
            'c_max' => $cMax,
            'elapsed_hours' => self::resolveElapsedHours($device, $reading),
            'hour_sin' => sin(2 * M_PI * $hour / 24.0),
            'hour_cos' => cos(2 * M_PI * $hour / 24.0),
            'dow_sin' => sin(2 * M_PI * $dow / 7.0),
            'dow_cos' => cos(2 * M_PI * $dow / 7.0),
            'is_daytime' => ($hour >= 6 && $hour < 18) ? 1 : 0,
            'is_cabai' => self::resolveIsCabai($device),
            'has_full_data' => self::resolveHasFullData($reading),
            'suhu_udara_lag1' => $tempLag1,
            'kelembapan_tanah_lag1' => $moistureLag1,
            'co2_lag1' => $co2Lag1,
            'suhu_udara_roll6' => $tempRoll6,
            'kelembapan_tanah_roll6' => $moistureRoll6,
            'vpd_approx' => self::calcVpdApprox($temp, $humidity),
        ];
    }

    private static function clip(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    private static function calcTScalar(float $temp): float
    {
        if ($temp <= self::T_MIN || $temp >= self::T_MAX) {
            return 0.0;
        }

        $num = ($temp - self::T_MIN) * ($temp - self::T_MAX);
        $denom = $num - ($temp - self::T_OPT) ** 2 + 1e-9;

        if ($denom == 0.0) {
            return 0.0;
        }

        return self::clip($num / $denom, 0.0, 1.0);
    }

    private static function calcReco(float $temp): float
    {
        $tempK = $temp + 273.15;
        $denom = $tempK - self::T0_K;

        if ($denom <= 0) {
            return 0.0;
        }

        return max(0.0, self::R_REF * exp(self::E0 * (1.0 / (self::T_REF_K - self::T0_K) - 1.0 / $denom)));
    }

    /**
     * Persamaan Tetens (meteorologi baku) untuk defisit tekanan uap (VPD),
     * dihitung deterministik dari suhu & kelembapan udara riil — menggantikan
     * placeholder bernoise pada skrip pelatihan dengan formula fisis nyata.
     */
    private static function calcVpdApprox(float $tempC, float $humidityPct): float
    {
        $es = 0.6108 * exp((17.27 * $tempC) / ($tempC + 237.3));
        $ea = $es * self::clip($humidityPct, 0.0, 100.0) / 100.0;

        return max(0.0, round($es - $ea, 4));
    }

    /**
     * REAL jika data jenis tanaman plot/kebun menyebut "cabai" secara eksplisit;
     * default 0 (bukan/tidak diketahui) jika tidak — bukan koin-lempar acak
     * seperti pada skrip pelatihan.
     */
    private static function resolveIsCabai(Device $device): int
    {
        $plantTypes = strtolower((string) (
            $device->garden?->plant_types ?? $device->landPlot?->plant_types ?? ''
        ));

        return str_contains($plantTypes, 'cabai') ? 1 : 0;
    }

    private static function resolveHasFullData(IotReading $reading): int
    {
        $requiredFields = [
            'air_temperature_sensor', 'air_humidity_sensor', 'air_pressure_hpa',
            'light_lux', 'soil_moisture', 'soil_temperature', 'soil_ph',
            'soil_n_mg_kg', 'soil_p_mg_kg', 'soil_k_mg_kg', 'co2_sensor', 'tvoc_ppb',
        ];

        foreach ($requiredFields as $field) {
            if ($reading->{$field} === null) {
                return 0;
            }
        }

        return 1;
    }

    private static function resolveLagValues(Device $device, IotReading $reading, float $tempFallback, float $moistureFallback, float $co2Fallback): array
    {
        $target = Carbon::parse($reading->reading_time)->subHour();

        $prior = IotReading::where('device_id', $device->id)
            ->where('reading_time', '<', $reading->reading_time)
            ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, reading_time, ?))', [$target])
            ->first();

        return [
            $prior->air_temperature_sensor ?? $tempFallback,
            $prior->soil_moisture ?? $moistureFallback,
            $prior->co2_sensor ?? $co2Fallback,
        ];
    }

    private static function resolveRollingAverages(Device $device, IotReading $reading, float $tempFallback, float $moistureFallback): array
    {
        $windowStart = Carbon::parse($reading->reading_time)->subHours(6);

        $window = IotReading::where('device_id', $device->id)
            ->where('reading_time', '<=', $reading->reading_time)
            ->where('reading_time', '>=', $windowStart)
            ->get();

        if ($window->isEmpty()) {
            return [$tempFallback, $moistureFallback];
        }

        return [
            (float) $window->avg('air_temperature_sensor'),
            (float) $window->avg('soil_moisture'),
        ];
    }

    private static function resolveElapsedHours(Device $device, IotReading $reading): float
    {
        $firstReading = IotReading::where('device_id', $device->id)
            ->orderBy('reading_time')
            ->first();

        $start = $firstReading ? Carbon::parse($firstReading->reading_time) : Carbon::parse($device->created_at);
        $current = Carbon::parse($reading->reading_time);

        return max(0.0, abs($current->getTimestamp() - $start->getTimestamp()) / 3600.0);
    }
}
