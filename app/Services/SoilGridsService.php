<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ══════════════════════════════════════════════════════════════════════
 * SoilGridsService — Integrasi Data SOC dari ISRIC SoilGrids 2.0
 * ══════════════════════════════════════════════════════════════════════
 *
 * Mengambil data Soil Organic Carbon (SOC) berdasarkan koordinat lahan
 * dari API publik SoilGrids 2.0 (gratis, tanpa API key).
 *
 * Ref: Poggio et al. (2021), "SoilGrids 2.0: Producing soil information
 *      for the globe with quantified spatial uncertainty", SOIL.
 *
 * Endpoint: https://rest.isric.org/soilgrids/v2.0/properties/query
 * Kedalaman: 0-30 cm (standar untuk analisis SOC pertanian)
 * Satuan: dg/kg (decigram per kilogram), dikonversi ke gC/m²
 */
class SoilGridsService
{
    const API_BASE = 'https://rest.isric.org/soilgrids/v2.0/properties/query';

    const CACHE_HOURS = 720; // Cache 30 hari (SOC berubah lambat)

    /**
     * Ambil SOC baseline dari SoilGrids berdasarkan koordinat.
     *
     * @return array ['soc_dg_kg' => float, 'soc_gc_m2' => float, 'source' => string]
     */
    public static function fetchSOC(float $latitude, float $longitude): array
    {
        $cacheKey = "soilgrids_soc_{$latitude}_{$longitude}";

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($latitude, $longitude) {
            try {
                $response = Http::timeout(3)->get(self::API_BASE, [
                    'lon' => $longitude,
                    'lat' => $latitude,
                    'property' => 'soc',
                    'depth' => '0-30cm',
                    'value' => 'mean',
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    // Parse respons SoilGrids
                    $socValue = $data['properties']['layers'][0]['depths'][0]['values']['mean'] ?? null;

                    if ($socValue !== null) {
                        // SoilGrids mengembalikan SOC dalam dg/kg (decigram per kilogram)
                        // Konversi ke gC/m² untuk kedalaman 0-30cm:
                        // SOC (g/kg) = dg/kg ÷ 10
                        // SOC (gC/m2) = SOC (g/kg) * bulk_density (kg/m3) * depth (m)
                        // Asumsi bulk density tanah pertanian: 1300 kg/m³
                        $socGKg = $socValue / 10;
                        $bulkDensity = 1300; // kg/m³
                        $depth = 0.30; // meter
                        $socGcM2 = $socGKg * $bulkDensity * $depth;

                        return [
                            'soc_dg_kg' => $socValue,
                            'soc_g_kg' => round($socGKg, 2),
                            'soc_gc_m2' => round($socGcM2, 2),
                            'source' => 'SoilGrids 2.0 (Poggio et al. 2021)',
                            'coordinate' => ['lat' => $latitude, 'lon' => $longitude],
                        ];
                    }
                }

                Log::warning("SoilGrids API returned no SOC data for [{$latitude}, {$longitude}]");

            } catch (\Exception $e) {
                Log::error('SoilGrids API error: '.$e->getMessage());
            }

            // Fallback: rata-rata SOC tanah pertanian tropis Indonesia
            // Ref: Minasny et al. (2017), "Soil carbon 4 per mille"
            return [
                'soc_dg_kg' => 150, // ~15 g/kg
                'soc_g_kg' => 15.0,
                'soc_gc_m2' => 5850.0, // 15 × 1300 × 0.30
                'source' => 'Fallback: rata-rata SOC tropis Indonesia',
                'coordinate' => ['lat' => $latitude, 'lon' => $longitude],
            ];
        });
    }
}
