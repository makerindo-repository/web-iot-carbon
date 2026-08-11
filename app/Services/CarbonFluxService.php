<?php

namespace App\Services;

use App\Models\CarbonDailyStock;
use App\Models\IotReading;

/**
 * ══════════════════════════════════════════════════════════════════════
 * CarbonFluxService — Estimasi Carbon Flux Berbasis Model LUE
 * ══════════════════════════════════════════════════════════════════════
 *
 * REFERENSI ILMIAH:
 * ─────────────────
 * Model ini dibangun berdasarkan kerangka Light Use Efficiency (LUE)
 * dari Monteith (1972) yang merupakan standar emas dalam ekologi
 * produktivitas tanaman:
 *
 *   GPP = PAR × fAPAR × ε_max × T_scalar × W_scalar × C_scalar
 *
 * Di mana:
 * - GPP   = Gross Primary Production (gC m⁻² per interval)
 * - PAR   = Photosynthetically Active Radiation (µmol m⁻² s⁻¹)
 * - fAPAR = Fraction of Absorbed PAR oleh kanopi (0–1)
 * - ε_max = Efisiensi penggunaan cahaya maksimum (gC MJ⁻¹)
 * - T/W/C = Scalar stres lingkungan (0–1)
 *
 * NPP (Net Primary Production):
 *   NPP = GPP - Ra
 * Di mana Ra = Autotrophic Respiration ≈ 53% GPP (sehingga NPP ≈ 47% GPP)
 * Ref: Waring et al. (1998), "Net primary production of forests"
 *
 * Net Ecosystem Exchange (NEE):
 *   NEE = GPP - RECO
 *
 * Carbon Potential Score (CPS / Sequestration Headroom):
 *   CPS = 1 - (C_current / C_max)
 * Ref: Dian et al. (2024), konsep soil carbon saturation deficit
 *
 * KONVENSI TANDA:
 * ───────────────
 * Nilai carbon_flux POSITIF = tanaman MENYERAP karbon (sink/baik)
 * Nilai carbon_flux NEGATIF = ekosistem MELEPAS karbon (source/buruk)
 *
 * CATATAN KETERBATASAN:
 * ─────────────────────
 * Model ini menggunakan data sensor IoT (konsentrasi CO₂ dalam ppm)
 * dan cahaya (Lux), BUKAN flux tower/eddy covariance sejati.
 * Hasilnya adalah ESTIMASI yang valid untuk perbandingan relatif
 * antar node dan tren temporal, bukan nilai absolut carbon budget.
 */
class CarbonFluxService
{
    // ═══════════════════════════════════════════════════════════════
    // LOOKUP TABLE: EPSILON (ε) PER JENIS TANAMAN
    // ═══════════════════════════════════════════════════════════════
    // Ref: Running et al. (2004) MODIS GPP Algorithm;
    //      Tian et al. (2023) — estimasi per spesies.
    //      Tanaman C4 memiliki ε lebih tinggi dari C3.
    // Satuan: gC MJ⁻¹
    // ═══════════════════════════════════════════════════════════════
    const EPSILON_TABLE = [
        // Tanaman pangan C3
        'padi' => 1.24,
        'rice' => 1.24,
        'kedelai' => 1.10,
        'soybean' => 1.10,
        'kentang' => 1.10,
        'potato' => 1.10,
        'tomat' => 1.05,
        'tomato' => 1.05,
        'cabai' => 1.00,
        'pepper' => 1.00,
        'gandum' => 1.15,
        'wheat' => 1.15,

        // Tanaman pangan C4
        'jagung' => 1.80,
        'corn' => 1.80,
        'maize' => 1.80,
        'tebu' => 1.95,
        'sugarcane' => 1.95,
        'sorgum' => 1.70,
        'sorghum' => 1.70,

        // Tanaman perkebunan (C3 perennial)
        'kopi' => 0.95,
        'coffee' => 0.95,
        'teh' => 0.85,
        'tea' => 0.85,
        'kelapa sawit' => 1.15,
        'sawit' => 1.15,
        'oil palm' => 1.15,
        'palm' => 1.15,
        'karet' => 1.00,
        'rubber' => 1.00,
        'kakao' => 0.90,
        'cacao' => 0.90,
        'cocoa' => 0.90,
        'kelapa' => 1.05,
        'coconut' => 1.05,

        // Tanaman umbi
        'singkong' => 1.30,
        'cassava' => 1.30,
        'ubi' => 1.20,

        // Default C3 tropis
        'default' => 1.20,
    ];

    // ═══════════════════════════════════════════════════════════════
    // LOOKUP TABLE: fAPAR PER JENIS TANAMAN
    // ═══════════════════════════════════════════════════════════════
    // Alternatif ilmiah ketika data satelit MODIS/Sentinel
    // belum tersedia. Nilai diambil dari literatur ekologi kanopi.
    // Ref: Sellers et al. (1992); Zhao et al. (2006) MODIS.
    // Catatan: Nilai ini adalah rata-rata musim tanam aktif.
    // ═══════════════════════════════════════════════════════════════
    const FAPAR_TABLE = [
        // Tanaman pangan
        'padi' => 0.78,
        'rice' => 0.78,
        'jagung' => 0.82,
        'corn' => 0.82,
        'maize' => 0.82,
        'kedelai' => 0.72,
        'soybean' => 0.72,
        'tebu' => 0.85,
        'sugarcane' => 0.85,
        'kentang' => 0.60,
        'potato' => 0.60,
        'tomat' => 0.58,
        'tomato' => 0.58,
        'cabai' => 0.55,
        'pepper' => 0.55,
        'gandum' => 0.75,
        'wheat' => 0.75,
        'sorgum' => 0.78,
        'sorghum' => 0.78,

        // Tanaman perkebunan
        'kopi' => 0.60,
        'coffee' => 0.60,
        'teh' => 0.70,
        'tea' => 0.70,
        'kelapa sawit' => 0.80,
        'sawit' => 0.80,
        'oil palm' => 0.80,
        'palm' => 0.80,
        'karet' => 0.75,
        'rubber' => 0.75,
        'kakao' => 0.55,
        'cacao' => 0.55,
        'cocoa' => 0.55,
        'kelapa' => 0.65,
        'coconut' => 0.65,

        // Tanaman umbi
        'singkong' => 0.65,
        'cassava' => 0.65,
        'ubi' => 0.60,

        // Default (kanopi parsial campuran)
        'default' => 0.63,
    ];

    // ═══════════════════════════════════════════════════════════════
    // KONSTANTA BIOFISIK
    // ═══════════════════════════════════════════════════════════════

    /**
     * Fraksi Respirasi Autotrofik (Ra / GPP).
     * Ref: Waring et al. (1998) — NPP/GPP ≈ 0.47,
     *      maka Ra/GPP ≈ 0.53 (karena NPP = GPP - Ra).
     */
    const AUTOTROPHIC_RESP_FRACTION = 0.53;

    /**
     * Parameter suhu optimal untuk tanaman C3 tropis.
     * Ref: Farquhar, von Caemmerer & Berry (1980)
     */
    const T_MIN = 10.0;

    const T_OPT = 28.0;

    const T_MAX = 40.0;

    /**
     * Kapasitas lapang tanah (field capacity).
     * Ref: FAO Irrigation & Drainage Paper No. 56.
     */
    const FIELD_CAPACITY = 60.0;

    /**
     * Baseline CO₂ atmosfer global (2024).
     * Ref: NOAA Global Monitoring Laboratory — Mauna Loa.
     */
    const CO2_BASELINE = 420.0;

    const DEFAULT_SOC_BASELINE_GC_M2 = 5850.0;

    /**
     * Konversi Lux → PAR (µmol m⁻² s⁻¹).
     * Ref: Thimijan & Heins (1983)
     */
    const LUX_TO_PAR = 0.0185;

    /**
     * Konversi PAR (µmol) → MJ per jam.
     * Ref: McCree (1972)
     */
    const PAR_TO_MJ_PER_HOUR = 0.00756;

    /**
     * Lloyd-Taylor RECO parameters.
     * Ref: Lloyd & Taylor (1994)
     */
    const R_REF = 2.0;       // µmol m⁻² s⁻¹ — respirasi dasar

    const T_REF_K = 283.15;  // 10°C dalam Kelvin

    const T0_K = 227.13;     // Parameter Lloyd-Taylor

    const E0 = 308.56;       // Energi aktivasi (K)

    // ═══════════════════════════════════════════════════════════════
    // FUNGSI UTAMA
    // ═══════════════════════════════════════════════════════════════

    /**
     * Hitung carbon flux dari satu pembacaan IoT.
     * Secara otomatis membaca jenis tanaman dari relasi Device → Garden → Plant
     * untuk menentukan ε (epsilon) dan fAPAR dari lookup table.
     */
    public static function calculate(IotReading $reading, float $intervalHours = 1.0): array
    {
        if ($reading->light_lux === null) {
            return [
                'carbon_flux' => 0.0,
                'gpp' => 0.0,
                'reco' => 0.0,
                'npp' => 0.0,
                'ra' => 0.0,
                'co2_sequestered' => 0.0,
                'breakdown' => [],
            ];
        }
        // ───────────────────────────────────────────────────────
        // LANGKAH 0: Resolve Jenis Tanaman untuk Lookup Table
        // ───────────────────────────────────────────────────────
        $plantType = self::resolvePlantType($reading);
        $epsilonMax = self::getEpsilon($plantType);
        $fAPAR = self::getFapar($plantType);

        // ───────────────────────────────────────────────────────
        // LANGKAH 1: Konversi Lux → PAR → MJ
        // ───────────────────────────────────────────────────────
        $lux = $reading->light_lux ?? 0;
        $par = $lux * self::LUX_TO_PAR;
        $parMJ = $par * self::PAR_TO_MJ_PER_HOUR * $intervalHours;

        $lightDrive = min(1.0, $par / 1500);

        // ───────────────────────────────────────────────────────
        // LANGKAH 2: fAPAR — Dari Lookup Table per Jenis Tanaman
        // ───────────────────────────────────────────────────────
        // Sebelumnya: hardcode Beer-Lambert LAI=2.0 (selalu 0.632)
        // Sekarang: lookup table berdasarkan jenis tanaman dari
        // literatur kanopi (Sellers et al. 1992, Zhao et al. 2006)
        // → sudah di-resolve di LANGKAH 0

        // ───────────────────────────────────────────────────────
        // LANGKAH 3: T_scalar — Stres Suhu
        // ───────────────────────────────────────────────────────
        $temp = $reading->air_temperature_sensor ?? 25;
        $tScalar = self::calculateTempScalar($temp);

        // ───────────────────────────────────────────────────────
        // LANGKAH 4: W_scalar — Stres Air
        // ───────────────────────────────────────────────────────
        // Node Carbon di lapangan (AGRISENSE-CC-00x) tidak punya sensor tanah
        // fisik, jadi soil_moisture/soil_temperature/soil_ph tersimpan 0 di
        // SEMUA baris tanpa sensor (bukan hasil ukur — IotReadingController
        // selalu insert 0 saat firmware tidak kirim soil_7in1). Kalau memakai
        // 0 apa adanya, w_scalar terkunci ke lantai stres 0.1 selamanya,
        // menekan GPP/NEE/CPS 10x dari kondisi wajar. Nol di ketiga kolom
        // sekaligus = tidak ada sensor terpasang → netral (tidak diasumsikan
        // stres). Nol hanya pada moisture (temp/pH tetap terisi nyata) tetap
        // dianggap pembacaan asli tanah kering.
        $hasSoilSensor = ! ($reading->soil_moisture == 0 && $reading->soil_temperature == 0 && $reading->soil_ph == 0);
        $soilMoisture = $reading->soil_moisture ?? 30;
        $wScalar = $hasSoilSensor
            ? min(1.0, max(0.1, $soilMoisture / self::FIELD_CAPACITY))
            : 1.0;

        // ───────────────────────────────────────────────────────
        // LANGKAH 5: C_scalar — Modulasi CO₂
        // ───────────────────────────────────────────────────────
        $co2 = $reading->co2_sensor ?? self::CO2_BASELINE;
        $cScalar = min(1.2, max(0.7, $co2 / self::CO2_BASELINE));

        // ───────────────────────────────────────────────────────
        // LANGKAH 6: Hitung GPP
        // ───────────────────────────────────────────────────────
        $gpp = $parMJ * $fAPAR * $epsilonMax * $tScalar * $wScalar * $cScalar;

        // ───────────────────────────────────────────────────────
        // LANGKAH 7: Hitung RECO (Lloyd-Taylor 1994)
        // ───────────────────────────────────────────────────────
        $taK = $temp + 273.15;
        $reco_umol = self::R_REF * exp(self::E0 * (1 / (self::T_REF_K - self::T0_K) - 1 / ($taK - self::T0_K)));
        $reco = $reco_umol * 0.0432 * $intervalHours;

        // ───────────────────────────────────────────────────────
        // LANGKAH 8: Hitung NEE (Net Ecosystem Exchange)
        // ───────────────────────────────────────────────────────
        $nee = $gpp - $reco;

        // ───────────────────────────────────────────────────────
        // LANGKAH 9: Hitung NPP (Net Primary Production)
        // ───────────────────────────────────────────────────────
        // NPP = GPP - Ra, di mana Ra ≈ 53% GPP
        // → NPP ≈ 47% GPP (Waring et al. 1998: NPP/GPP ≈ 0.47)
        $ra = $gpp * self::AUTOTROPHIC_RESP_FRACTION;
        $npp = $gpp - $ra;

        // ───────────────────────────────────────────────────────
        // LANGKAH 10: Konversi ke CO₂ equivalent
        // ───────────────────────────────────────────────────────
        $co2Sequestered = $nee * 3.67;

        return [
            'carbon_flux' => round($nee, 4),
            'gpp' => round($gpp, 4),
            'reco' => round($reco, 4),
            'npp' => round($npp, 4),
            'ra' => round($ra, 4),
            'co2_sequestered' => round($co2Sequestered, 4),
            'breakdown' => [
                'par_umol' => round($par, 2),
                'par_mj' => round($parMJ, 6),
                'fAPAR' => round($fAPAR, 3),
                'fAPAR_source' => 'lookup_table',
                'plant_type' => $plantType,
                'light_drive' => round($lightDrive, 3),
                't_scalar' => round($tScalar, 3),
                'w_scalar' => round($wScalar, 3),
                'c_scalar' => round($cScalar, 3),
                'epsilon_max' => $epsilonMax,
            ],
        ];
    }

    /**
     * Hitung carbon flux dan simpan hasilnya ke kolom iot_readings.
     */
    public static function calculateAndStore(IotReading $reading, float $intervalHours = 1.0): array
    {
        $result = self::calculate($reading, $intervalHours);

        $reading->update([
            'carbon_flux' => $result['carbon_flux'],
        ]);

        return $result;
    }

    /**
     * Estimasi total serapan karbon harian dari pembacaan-pembacaan terbaru.
     */
    public static function estimateDailyCarbon(string $deviceId, ?string $date = null): array
    {
        $date = $date ?? now()->format('Y-m-d');

        $readings = IotReading::where('device_id', $deviceId)
            ->whereDate('reading_time', $date)
            ->orderBy('reading_time', 'asc')
            ->get();

        if ($readings->isEmpty()) {
            return [
                'daily_gpp' => 0,
                'daily_nee' => 0,
                'daily_npp' => 0,
                'daily_co2_g_m2' => 0,
                'daily_co2_kg_ha' => 0,
                'readings_count' => 0,
            ];
        }

        $totalGPP = 0;
        $totalReco = 0;
        $totalNEE = 0;
        $totalNPP = 0;

        foreach ($readings as $i => $reading) {
            $interval = 1.0;
            if ($i > 0) {
                $prev = $readings[$i - 1];
                $diffMinutes = $reading->reading_time->diffInMinutes($prev->reading_time);
                $interval = max(0.1, $diffMinutes / 60);
            }

            $result = self::calculate($reading, $interval);
            $totalGPP += $result['gpp'];
            $totalReco += $result['reco'];
            $totalNEE += $result['carbon_flux'];
            $totalNPP += $result['npp'];
        }

        $totalCO2 = $totalNEE * 3.67;
        $co2KgHa = ($totalCO2 * 10000) / 1000;

        return [
            'daily_gpp' => round($totalGPP, 4),
            'daily_reco' => round($totalReco, 4),
            'daily_nee' => round($totalNEE, 4),
            'daily_npp' => round($totalNPP, 4),
            'daily_co2_g_m2' => round($totalCO2, 4),
            'daily_co2_kg_ha' => round($co2KgHa, 2),
            'readings_count' => $readings->count(),
        ];
    }

    /**
     * Simpan agregasi stok karbon harian.
     *
     * Cumulative NPP merepresentasikan C_biomass,acc pada dokumen metodologi:
     * C_current = SOC_baseline + C_biomass,acc.
     */
    public static function updateDailyStock(IotReading $reading): ?CarbonDailyStock
    {
        if (! $reading->device_id || ! $reading->reading_time) {
            return null;
        }

        $date = $reading->reading_time->format('Y-m-d');
        $daily = self::estimateDailyCarbon((string) $reading->device_id, $date);

        $previousCumulative = (float) CarbonDailyStock::where('device_id', $reading->device_id)
            ->whereDate('stock_date', '<', $date)
            ->orderByDesc('stock_date')
            ->value('cumulative_npp_gc_m2');

        return CarbonDailyStock::updateOrCreate(
            [
                'device_id' => $reading->device_id,
                'stock_date' => $date,
            ],
            [
                'daily_gpp_gc_m2' => $daily['daily_gpp'],
                'daily_reco_gc_m2' => $daily['daily_reco'],
                'daily_npp_gc_m2' => $daily['daily_npp'],
                'daily_nee_gc_m2' => $daily['daily_nee'],
                'cumulative_npp_gc_m2' => round($previousCumulative + $daily['daily_npp'], 4),
                'readings_count' => $daily['readings_count'],
            ]
        );
    }

    /**
     * Hitung CPS (Carbon Potential Score) — Sequestration Headroom.
     * Ref: Dian et al. (2024) — konsep soil carbon saturation deficit.
     *
     * @param  float  $cCurrent  Stok karbon saat ini (gC m⁻²)
     * @param  float  $cMax  Kapasitas maksimum penyimpanan karbon (gC m⁻²)
     * @return float CPS antara 0.0 dan 1.0
     */
    public static function calculateCPS(float $cCurrent, float $cMax): float
    {
        if ($cMax <= 0) {
            return 1.0; // Jika C_max tidak diketahui, anggap lahan masih kosong
        }

        $cps = 1 - ($cCurrent / $cMax);

        return max(0.0, min(1.0, round($cps, 4)));
    }

    /**
     * Estimasi C_max dari SOC baseline.
     * Berdasarkan konsep carbon saturation (Hassink 1997; Breure et al. 2025).
     * C_max ≈ SOC_baseline × faktor kapasitas (default 2.0 untuk tanah tropis).
     *
     * @param  float  $socBaseline  SOC dari SoilGrids (gC m⁻² pada 0-30cm)
     * @param  float  $saturationFactor  Faktor kapasitas (default: 2.0)
     * @return float C_max dalam gC m⁻²
     */
    public static function estimateCMax(float $socBaseline, float $saturationFactor = 2.0): float
    {
        if ($socBaseline <= 0) {
            // Fallback selaras dokumen metodologi:
            // 15 g/kg * 1300 kg/m3 * 0.30 m = 5850 gC/m2.
            $socBaseline = self::DEFAULT_SOC_BASELINE_GC_M2;
        }

        return $socBaseline * $saturationFactor;
    }

    // ═══════════════════════════════════════════════════════════════
    // FUNGSI HELPER
    // ═══════════════════════════════════════════════════════════════

    /**
     * Resolve jenis tanaman dari relasi IotReading → Device → Garden → Plant.
     */
    private static function resolvePlantType(IotReading $reading): string
    {
        try {
            $device = $reading->device;
            if (! $device) {
                return 'default';
            }

            // Prioritas: Plant model name → Komoditi nama → garden plant_types → default
            $plantName = $device->garden?->plant?->name
                         ?? $device->garden?->komoditi?->nama_komoditi
                         ?? $device->garden?->plant_types
                         ?? 'default';

            // Normalisasi: ambil kata pertama, lowercase, trim
            $normalized = strtolower(trim(explode(',', $plantName)[0]));

            return $normalized ?: 'default';
        } catch (\Throwable $e) {
            // \Throwable catches both \Exception and \Error (e.g. TypeError
            // when Eloquent resolver is null in pure unit tests without Laravel boot)
            return 'default';
        }
    }

    /**
     * Ambil ε (epsilon) dari lookup table berdasarkan jenis tanaman.
     */
    public static function getEpsilon(string $plantType): float
    {
        $key = strtolower(trim($plantType));

        return self::EPSILON_TABLE[$key] ?? self::EPSILON_TABLE['default'];
    }

    /**
     * Ambil fAPAR dari lookup table berdasarkan jenis tanaman.
     */
    public static function getFapar(string $plantType): float
    {
        $key = strtolower(trim($plantType));

        return self::FAPAR_TABLE[$key] ?? self::FAPAR_TABLE['default'];
    }

    /**
     * Kurva respons suhu parabola.
     * Mensimulasikan aktivitas enzim RuBisCO.
     */
    private static function calculateTempScalar(float $temp): float
    {
        if ($temp <= self::T_MIN || $temp >= self::T_MAX) {
            return 0.0;
        }

        $numerator = ($temp - self::T_MIN) * (self::T_MAX - $temp);
        $denominator = (self::T_OPT - self::T_MIN) * (self::T_MAX - self::T_OPT);

        if ($denominator <= 0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $numerator / $denominator));
    }
}
