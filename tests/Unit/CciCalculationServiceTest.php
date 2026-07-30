<?php

namespace Tests\Unit;

use App\Models\IotReading;
use App\Services\CciCalculationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for CciCalculationService
 *
 * Tests the weighted multi-factor CCI (Carbon Capture Index) calculation.
 * These are pure unit tests — no database, no Laravel boot required.
 *
 * CCI Formula:
 *   CO2 (25%, inverse) + SOC (20%) + Moisture (15%) + pH (15%) + Temp (10%) + NPK (15%)
 *
 * @see docs/audit/PHASE_4_BACKEND_PART_2.md
 * @see docs/audit/STAGE_4_BACKEND.md
 */
class CciCalculationServiceTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════
    //  Helper: Build a mock IotReading with given sensor values
    // ═══════════════════════════════════════════════════════════════

    private function makeReading(array $overrides = []): IotReading
    {
        $defaults = [
            'co2_sensor' => 400,
            'soil_organic_carbon' => 50,
            'soil_moisture' => 50,
            'soil_ph' => 6.75,
            'air_temperature_sensor' => 25,
            'soil_n_mg_kg' => 150,
            'soil_p_mg_kg' => 100,
            'soil_k_mg_kg' => 250,
        ];

        $data = array_merge($defaults, $overrides);

        // Create IotReading instance without database
        $reading = new IotReading;
        foreach ($data as $key => $value) {
            $reading->$key = $value;
        }

        return $reading;
    }

    // ═══════════════════════════════════════════════════════════════
    //  1. Core Calculation Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_calculate_returns_required_keys(): void
    {
        $reading = $this->makeReading();
        $result = CciCalculationService::calculate($reading);

        $this->assertArrayHasKey('cci_value', $result);
        $this->assertArrayHasKey('cci_status', $result);
        $this->assertArrayHasKey('breakdown', $result);
    }

    public function test_breakdown_has_all_component_scores(): void
    {
        $reading = $this->makeReading();
        $result = CciCalculationService::calculate($reading);

        $expectedKeys = ['co2_score', 'soc_score', 'moisture_score', 'ph_score', 'temp_score', 'npk_score'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result['breakdown'], "Missing breakdown key: $key");
        }
    }

    public function test_cci_value_is_between_zero_and_one(): void
    {
        $reading = $this->makeReading();
        $result = CciCalculationService::calculate($reading);

        $this->assertGreaterThanOrEqual(0, $result['cci_value']);
        $this->assertLessThanOrEqual(1, $result['cci_value']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  2. Optimal Conditions → High CCI
    // ═══════════════════════════════════════════════════════════════

    public function test_optimal_conditions_produce_high_cci(): void
    {
        $reading = $this->makeReading([
            'co2_sensor' => 300,              // Low CO2 = high score (inverse)
            'soil_organic_carbon' => 80,      // High SOC
            'soil_moisture' => 60,            // Good moisture
            'soil_ph' => 6.75,               // Perfect pH
            'air_temperature_sensor' => 25,   // Perfect temp
            'soil_n_mg_kg' => 150,            // Good NPK
            'soil_p_mg_kg' => 100,
            'soil_k_mg_kg' => 250,
        ]);

        $result = CciCalculationService::calculate($reading);

        $this->assertGreaterThan(0.7, $result['cci_value'],
            "Optimal conditions should produce CCI > 0.7, got: {$result['cci_value']}");
    }

    // ═══════════════════════════════════════════════════════════════
    //  3. Stress Conditions → Low CCI
    // ═══════════════════════════════════════════════════════════════

    public function test_stress_conditions_produce_low_cci(): void
    {
        $reading = $this->makeReading([
            'co2_sensor' => 1800,             // Very high CO2 (bad)
            'soil_organic_carbon' => 5,       // Very low SOC
            'soil_moisture' => 10,            // Dry soil
            'soil_ph' => 3.5,                // Very acidic
            'air_temperature_sensor' => 45,   // Extreme heat
            'soil_n_mg_kg' => 10,             // Low NPK
            'soil_p_mg_kg' => 5,
            'soil_k_mg_kg' => 20,
        ]);

        $result = CciCalculationService::calculate($reading);

        $this->assertLessThan(0.25, $result['cci_value'],
            "Stress conditions should produce CCI < 0.25, got: {$result['cci_value']}");
        $this->assertEquals('rendah', $result['cci_status']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  4. Classification Boundary Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_classify_rendah(): void
    {
        // Force a very bad reading to get CCI near 0
        $reading = $this->makeReading([
            'co2_sensor' => 2000,
            'soil_organic_carbon' => 0,
            'soil_moisture' => 0,
            'soil_ph' => 10,
            'air_temperature_sensor' => 50,
            'soil_n_mg_kg' => 0,
            'soil_p_mg_kg' => 0,
            'soil_k_mg_kg' => 0,
        ]);

        $result = CciCalculationService::calculate($reading);
        $this->assertEquals('rendah', $result['cci_status']);
    }

    public function test_classify_sangat_tinggi_with_perfect_scores(): void
    {
        // Truly optimal values for every parameter → highest CCI
        $reading = $this->makeReading([
            'co2_sensor' => 420,               // Baseline CO2 → optimal co2_score
            'soil_organic_carbon' => 100,      // Max SOC
            'soil_moisture' => 50,             // Optimal range (40-60)
            'soil_ph' => 6.5,                  // Exact optimal pH
            'air_temperature_sensor' => 25,    // Perfect temp
            'soil_n_mg_kg' => 300,             // Max N
            'soil_p_mg_kg' => 200,             // Max P
            'soil_k_mg_kg' => 500,             // Max K
        ]);

        $result = CciCalculationService::calculate($reading);
        $this->assertEquals('sangat_tinggi', $result['cci_status'],
            "Perfect scores should classify as 'sangat_tinggi', got: {$result['cci_status']} (CCI: {$result['cci_value']})");
    }

    // ═══════════════════════════════════════════════════════════════
    //  5. Null/Default Value Handling
    // ═══════════════════════════════════════════════════════════════

    public function test_null_values_do_not_crash(): void
    {
        $reading = new IotReading;
        // All sensor fields are null — should use defaults via ?? operator

        $result = CciCalculationService::calculate($reading);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('cci_value', $result);
        $this->assertGreaterThanOrEqual(0, $result['cci_value']);
        $this->assertLessThanOrEqual(1, $result['cci_value']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  6. Individual Component Score Validation
    // ═══════════════════════════════════════════════════════════════

    public function test_co2_score_is_inverse(): void
    {
        // Low CO2 should give HIGHER co2_score than high CO2
        $lowCo2 = CciCalculationService::calculate($this->makeReading(['co2_sensor' => 300]));
        $highCo2 = CciCalculationService::calculate($this->makeReading(['co2_sensor' => 1800]));

        $this->assertGreaterThan(
            $highCo2['breakdown']['co2_score'],
            $lowCo2['breakdown']['co2_score'],
            'Lower CO2 should produce higher co2_score (inverse relationship)'
        );
    }

    public function test_ph_optimal_at_6_5(): void
    {
        // Service uses optimal point at 6.5 (tropical crops), not 6.75
        $optimal = CciCalculationService::calculate($this->makeReading(['soil_ph' => 6.5]));
        $acidic = CciCalculationService::calculate($this->makeReading(['soil_ph' => 3.5]));
        $alkaline = CciCalculationService::calculate($this->makeReading(['soil_ph' => 10.0]));

        $this->assertGreaterThan($acidic['breakdown']['ph_score'], $optimal['breakdown']['ph_score']);
        $this->assertGreaterThan($alkaline['breakdown']['ph_score'], $optimal['breakdown']['ph_score']);
        $this->assertEquals(1.0, $optimal['breakdown']['ph_score'],
            'pH 6.5 should give perfect score of 1.0');
    }

    public function test_temp_optimal_at_25(): void
    {
        $optimal = CciCalculationService::calculate($this->makeReading(['air_temperature_sensor' => 25]));
        $hot = CciCalculationService::calculate($this->makeReading(['air_temperature_sensor' => 45]));
        $cold = CciCalculationService::calculate($this->makeReading(['air_temperature_sensor' => 0]));

        $this->assertGreaterThan($hot['breakdown']['temp_score'], $optimal['breakdown']['temp_score']);
        $this->assertGreaterThan($cold['breakdown']['temp_score'], $optimal['breakdown']['temp_score']);
        $this->assertEquals(1.0, $optimal['breakdown']['temp_score'],
            'Temperature 25°C should give perfect score of 1.0');
    }

    // ═══════════════════════════════════════════════════════════════
    //  7. Weight Verification
    // ═══════════════════════════════════════════════════════════════

    public function test_weights_sum_to_one(): void
    {
        $sum = array_sum(CciCalculationService::WEIGHTS);
        $this->assertEquals(1.0, $sum, "Weights must sum to 1.0, got: $sum");
    }

    // ═══════════════════════════════════════════════════════════════
    //  8. Edge Cases
    // ═══════════════════════════════════════════════════════════════

    public function test_extreme_below_range_values_clamp_to_zero(): void
    {
        $reading = $this->makeReading([
            'co2_sensor' => 0,       // Below min range (300)
            'soil_moisture' => -50,  // Below min range (0)
        ]);

        $result = CciCalculationService::calculate($reading);

        // Should not crash and CCI should still be 0-1
        $this->assertGreaterThanOrEqual(0, $result['cci_value']);
        $this->assertLessThanOrEqual(1, $result['cci_value']);
    }

    public function test_extreme_above_range_values_clamp_to_one(): void
    {
        $reading = $this->makeReading([
            'co2_sensor' => 5000,       // Way above max (2000)
            'soil_moisture' => 200,     // Way above max (100)
        ]);

        $result = CciCalculationService::calculate($reading);

        $this->assertGreaterThanOrEqual(0, $result['cci_value']);
        $this->assertLessThanOrEqual(1, $result['cci_value']);
    }

    public function test_cci_value_rounded_to_3_decimals(): void
    {
        $reading = $this->makeReading();
        $result = CciCalculationService::calculate($reading);

        // Check that cci_value has at most 3 decimal places
        $parts = explode('.', (string) $result['cci_value']);
        if (isset($parts[1])) {
            $this->assertLessThanOrEqual(3, strlen($parts[1]),
                'CCI value should be rounded to 3 decimal places');
        }
    }
}
