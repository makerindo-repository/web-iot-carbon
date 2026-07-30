<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiInsightControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_ai_insight_rule_based_returns_successful_json(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);

        $plotId = DB::table('land_plots')->insertGetId([
            'plot_code' => 'PLOT-TEST-001',
            'plot_name' => 'Plot Test',
            'owner_name' => 'Tester',
            'address' => 'Bandung',
            'latitude' => -6.9175,
            'longitude' => 107.6191,
            'area_hectare' => 1.25,
            'plant_types' => 'padi',
            'soc_baseline_gc_m2' => 5850,
            'c_max_gc_m2' => 11700,
            'soc_source' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deviceId = DB::table('devices')->insertGetId([
            'device_code' => 'AGRISENSE-TEST-001',
            'plot_id' => $plotId,
            'device_status' => 'online',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('iot_readings')->insert([
            'device_id' => $deviceId,
            'plot_id' => $plotId,
            'reading_time' => now(),
            'air_temperature_sensor' => 27.5,
            'air_humidity_sensor' => 72.0,
            'soil_temperature' => 25.0,
            'soil_moisture' => 55.0,
            'soil_ph' => 6.8,
            'co2_sensor' => 430.0,
            'soil_organic_carbon' => 1.5,
            'carbon_flux' => 0.25,
            'light_lux' => 18000,
            'data_valid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/ai-insight/generate', [
                'node_id' => 'AGRISENSE-TEST-001',
                'time_range' => '24h',
                'force_rule_based' => true,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('provider', 'rule-based')
            ->assertJsonStructure([
                'analisis',
                'rekomendasi',
                'time_range',
                'generated_at',
                'provider',
            ]);
    }
}
