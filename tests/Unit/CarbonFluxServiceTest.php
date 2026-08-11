<?php

namespace Tests\Unit;

use App\Models\IotReading;
use App\Services\CarbonFluxService;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for CarbonFluxService
 *
 * Tests the LUE-based carbon flux estimation model.
 * Formula: GPP = PAR_MJ × fAPAR × ε_max × T_scalar × W_scalar × C_scalar
 * NEE = GPP - RECO, NPP = 0.47 * GPP
 *
 * These are pure unit tests — no database, no Laravel boot required.
 */
class CarbonFluxServiceTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════
    //  Helper
    // ═══════════════════════════════════════════════════════════════

    private function makeReading(array $overrides = []): IotReading
    {
        $defaults = [
            'light_lux' => 50000,             // Bright daylight
            'air_temperature_sensor' => 28,    // Optimal for tropical C3
            'soil_moisture' => 60,             // Field capacity
            'co2_sensor' => 420,               // Global baseline
        ];

        $data = array_merge($defaults, $overrides);

        $reading = new IotReading;
        foreach ($data as $key => $value) {
            $reading->$key = $value;
        }

        return $reading;
    }

    // ═══════════════════════════════════════════════════════════════
    //  1. Structure & Key Validation
    // ═══════════════════════════════════════════════════════════════

    public function test_calculate_returns_required_keys(): void
    {
        $result = CarbonFluxService::calculate($this->makeReading());

        $this->assertArrayHasKey('carbon_flux', $result);
        $this->assertArrayHasKey('gpp', $result);
        $this->assertArrayHasKey('co2_sequestered', $result);
        $this->assertArrayHasKey('breakdown', $result);
    }

    public function test_breakdown_has_all_scalar_components(): void
    {
        $result = CarbonFluxService::calculate($this->makeReading());
        $breakdown = $result['breakdown'];

        $expectedKeys = ['par_umol', 'par_mj', 'fAPAR', 'light_drive', 't_scalar', 'w_scalar', 'c_scalar', 'epsilon_max'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $breakdown, "Missing breakdown key: $key");
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  2. Light / PAR Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_zero_light_produces_negative_flux_due_to_respiration(): void
    {
        $result = CarbonFluxService::calculate($this->makeReading(['light_lux' => 0]));

        $this->assertLessThan(0, $result['carbon_flux'], 'Zero light should produce negative carbon flux (respiration only)');
        $this->assertEquals(0, $result['gpp'], 'Zero light should produce zero GPP');
    }

    public function test_more_light_produces_more_flux(): void
    {
        $low = CarbonFluxService::calculate($this->makeReading(['light_lux' => 10000]));
        $high = CarbonFluxService::calculate($this->makeReading(['light_lux' => 80000]));

        $this->assertGreaterThan($low['carbon_flux'], $high['carbon_flux'],
            'Higher light should produce more carbon flux');
    }

    // ═══════════════════════════════════════════════════════════════
    //  3. Temperature Scalar Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_temp_below_minimum_produces_negative_flux(): void
    {
        // T_MIN = 10°C; below this, photosynthesis stops but respiration continues
        $result = CarbonFluxService::calculate($this->makeReading([
            'air_temperature_sensor' => 5,
            'light_lux' => 50000,
        ]));

        $this->assertLessThan(0, $result['carbon_flux'],
            'Temperature below T_MIN (10°C) should produce negative flux due to respiration');
        $this->assertEquals(0, $result['breakdown']['t_scalar']);
    }

    public function test_temp_above_maximum_produces_negative_flux(): void
    {
        // T_MAX = 40°C; above this, enzymes denature but respiration is high
        $result = CarbonFluxService::calculate($this->makeReading([
            'air_temperature_sensor' => 45,
            'light_lux' => 50000,
        ]));

        $this->assertLessThan(0, $result['carbon_flux'],
            'Temperature above T_MAX (40°C) should produce negative flux due to high respiration');
        $this->assertEquals(0, $result['breakdown']['t_scalar']);
    }

    public function test_temp_at_optimal_gives_maximum_scalar(): void
    {
        // T_OPT = 28°C
        $result = CarbonFluxService::calculate($this->makeReading([
            'air_temperature_sensor' => 28,
        ]));

        $this->assertEquals(1.0, $result['breakdown']['t_scalar'],
            'Temperature at T_OPT (28°C) should give t_scalar = 1.0');
    }

    // ═══════════════════════════════════════════════════════════════
    //  4. Water Scalar Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_full_moisture_gives_w_scalar_one(): void
    {
        // FIELD_CAPACITY = 60; moisture >= 60 → w_scalar = 1.0
        $result = CarbonFluxService::calculate($this->makeReading(['soil_moisture' => 60]));
        $this->assertEquals(1.0, $result['breakdown']['w_scalar']);
    }

    public function test_low_moisture_reduces_w_scalar(): void
    {
        $wet = CarbonFluxService::calculate($this->makeReading(['soil_moisture' => 60]));
        $dry = CarbonFluxService::calculate($this->makeReading(['soil_moisture' => 15]));

        $this->assertGreaterThan($dry['breakdown']['w_scalar'], $wet['breakdown']['w_scalar'],
            'Higher moisture should produce higher w_scalar');
    }

    public function test_w_scalar_has_minimum_floor(): void
    {
        // Sensor tanah nyata membaca kering total (moisture=0) tapi suhu/pH
        // tanah tetap terkirim → dianggap pembacaan asli, bukan sensor absen.
        $result = CarbonFluxService::calculate($this->makeReading([
            'soil_moisture' => 0,
            'soil_temperature' => 24,
            'soil_ph' => 6.2,
        ]));
        $this->assertGreaterThanOrEqual(0.1, $result['breakdown']['w_scalar'],
            'w_scalar should have minimum floor of 0.1');
    }

    public function test_no_soil_sensor_gives_neutral_w_scalar(): void
    {
        // Node Carbon di lapangan tidak punya sensor tanah fisik — moisture,
        // suhu tanah, dan pH semuanya 0 bersamaan (default insert, bukan
        // hasil ukur). w_scalar harus netral (1.0), bukan lantai stres 0.1,
        // supaya GPP/NEE/Carbon Potential Score tidak tertekan 10x tanpa dasar.
        $result = CarbonFluxService::calculate($this->makeReading([
            'soil_moisture' => 0,
            'soil_temperature' => 0,
            'soil_ph' => 0,
        ]));
        $this->assertEquals(1.0, $result['breakdown']['w_scalar'],
            'w_scalar should be neutral (1.0) when no soil sensor is present');
    }

    // ═══════════════════════════════════════════════════════════════
    //  5. CO2 Scalar Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_co2_at_baseline_gives_c_scalar_one(): void
    {
        $result = CarbonFluxService::calculate($this->makeReading(['co2_sensor' => 420]));
        $this->assertEquals(1.0, $result['breakdown']['c_scalar']);
    }

    public function test_co2_scalar_clamped_at_1_2(): void
    {
        // Very high CO2 → c_scalar capped at 1.2
        $result = CarbonFluxService::calculate($this->makeReading(['co2_sensor' => 2000]));
        $this->assertEquals(1.2, $result['breakdown']['c_scalar'],
            'c_scalar should be clamped at 1.2 for very high CO2');
    }

    public function test_co2_scalar_clamped_at_0_7(): void
    {
        // Very low CO2 → c_scalar floor at 0.7
        $result = CarbonFluxService::calculate($this->makeReading(['co2_sensor' => 100]));
        $this->assertEquals(0.7, $result['breakdown']['c_scalar'],
            'c_scalar should be clamped at 0.7 for very low CO2');
    }

    // ═══════════════════════════════════════════════════════════════
    //  6. GPP-to-NEE Relationship
    // ═══════════════════════════════════════════════════════════════

    public function test_nee_incorporates_reco_subtraction(): void
    {
        // NEE = GPP - RECO, so NEE should be less than GPP as long as temp allows respiration
        $result = CarbonFluxService::calculate($this->makeReading());

        $this->assertLessThan(
            $result['gpp'],
            $result['carbon_flux'],
            'carbon_flux (NEE) should be less than GPP due to RECO subtraction'
        );
    }

    public function test_co2_sequestered_is_nee_times_3_67(): void
    {
        // 1 gC = 3.67 gCO2 (molar mass ratio 44/12)
        $result = CarbonFluxService::calculate($this->makeReading());

        $this->assertEqualsWithDelta(
            $result['carbon_flux'] * 3.67,
            $result['co2_sequestered'],
            0.001,
            'co2_sequestered should be carbon_flux × 3.67'
        );
    }

    // ═══════════════════════════════════════════════════════════════
    //  7. Null/Default Value Handling
    // ═══════════════════════════════════════════════════════════════

    public function test_null_values_do_not_crash(): void
    {
        $reading = new IotReading;
        // All sensor fields are null — should use defaults via ?? operator

        $result = CarbonFluxService::calculate($reading);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('carbon_flux', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    //  8. Interval Parameter
    // ═══════════════════════════════════════════════════════════════

    public function test_longer_interval_produces_more_flux(): void
    {
        $reading = $this->makeReading();

        $oneHour = CarbonFluxService::calculate($reading, 1.0);
        $twoHours = CarbonFluxService::calculate($reading, 2.0);

        $this->assertEqualsWithDelta(
            $oneHour['carbon_flux'] * 2,
            $twoHours['carbon_flux'],
            0.0001,
            'Doubling the interval should double the carbon flux'
        );
    }

    // ═══════════════════════════════════════════════════════════════
    //  9. Constants Verification
    // ═══════════════════════════════════════════════════════════════

    public function test_epsilon_default_is_positive(): void
    {
        $this->assertGreaterThan(0, CarbonFluxService::EPSILON_TABLE['default']);
    }
}
