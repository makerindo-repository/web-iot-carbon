<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\IotReading;
use App\Models\LandPlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QC Edge Hunt Test Suite — 8 Vektor Attack
 * Target: AI & Analytics
 */
class QcAiEdgeHuntTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Device $device;

    private LandPlot $plot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->device = Device::create([
            'device_code' => 'NODE-TEST-01',
        ]);
        $this->plot = LandPlot::create([
            'plot_code' => 'PLOT-TEST-01',
            'plot_name' => 'Lahan Test',
            'area_hectare' => 1.5,
            'address' => 'Bandung',
            'latitude' => '-6.914744',
            'longitude' => '107.609810',
            'status' => 'aktif',
        ]);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🤖 AI INSIGHTS
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** V1: Boundary — Waktu Rentang (Time Range) Tidak Valid */
    public function test_ai_invalid_time_range(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/ai-insight/generate', ['node_id' => $this->device->id, 'time_range' => '100y']);

        // Validation rules harusnya memblokir time_range selain 24h, 7d, 30d
        $response->assertStatus(422);
    }

    /** V2: Null Strike — Node ID Kosong */
    public function test_ai_null_node(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/ai-insight/generate', ['time_range' => '24h']);

        $response->assertStatus(422);
    }

    /** V3: Type Confusion — Boolean pada time_range */
    public function test_ai_type_confusion(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/ai-insight/generate', ['node_id' => $this->device->id, 'time_range' => true]);

        $response->assertStatus(422);
    }

    /** V4: State Collision — AI API Down (Timeout/Error 500 dari LLM) */
    public function test_ai_fallback_on_api_down(): void
    {
        $this->actingAs($this->admin);

        // Tambahkan dummy reading agar tidak early return 'Belum ada data sensor'
        IotReading::create([
            'device_id' => $this->device->id,
            'plot_id' => $this->plot->id,
            'air_temperature_sensor' => 25.5,
            'air_humidity_sensor' => 70.0,
            'soil_temperature' => 24.0,
            'soil_moisture' => 50.0,
            'soil_ph' => 6.5,
            'co2_sensor' => 400.0,
            'soil_organic_carbon' => 2.5,
            'carbon_flux' => 1.2,
            'reading_time' => now(),
        ]);

        // Paksa panggil AI dengan parameter force_rule_based = true untuk mensimulasikan fallback
        $response = $this->postJson('/api/ai-insight/generate', [
            'node_id' => $this->device->id,
            'time_range' => '24h',
            'force_rule_based' => true,
        ]);

        $response->assertJsonStructure([
            'analisis',
            'rekomendasi',
        ]);

        // Pastikan provider ditandai sebagai rule-based (atau fallback)
        $this->assertEquals('rule-based', $response->json('provider'));
    }
}
