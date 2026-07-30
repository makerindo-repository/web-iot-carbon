<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\LandPlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Regresi untuk temuan audit 2026-07-04.
 *
 * Mengunci perbaikan:
 *  - M-2: KomoditiController — sub-payload skalar (valid-JSON tapi bukan array)
 *         sebelumnya lolos validasi lalu memicu TypeError pada array_merge → 500.
 *  - M-1: SystemController — pesan exception internal ($e->getMessage()) bocor
 *         ke response client.
 */
class AuditFixRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ── M-2: Komoditi sub-payload skalar tidak boleh crash 500 ──

    /** Sub-payload dikirim sebagai skalar integer (valid JSON, tapi bukan objek). */
    public function test_komoditi_scalar_int_subpayload_returns_422_not_500(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/komoditi', [
            'nama_komoditi' => 'Tomat',
            'kategori_tanaman' => 'Sayuran',
            'lingkungan' => 123, // skalar, bukan objek/asosiatif
        ]);

        $this->assertNotEquals(500, $response->getStatusCode());
        $response->assertStatus(422);
    }

    /** Sub-payload berupa JSON string yang men-decode ke skalar. */
    public function test_komoditi_scalar_json_string_subpayload_returns_422_not_500(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/komoditi', [
            'nama_komoditi' => 'Cabai',
            'kategori_tanaman' => 'Sayuran',
            'sensor' => '123', // string yang decode jadi int 123
        ]);

        $this->assertNotEquals(500, $response->getStatusCode());
        $response->assertStatus(422);
    }

    /** Elemen hama_penyakit skalar (bukan objek) tidak boleh crash 500. */
    public function test_komoditi_scalar_hama_penyakit_element_returns_422_not_500(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/komoditi', [
            'nama_komoditi' => 'Terong',
            'kategori_tanaman' => 'Sayuran',
            'hama_penyakit' => [1, 2, 3], // elemen skalar, seharusnya objek
        ]);

        $this->assertNotEquals(500, $response->getStatusCode());
        $response->assertStatus(422);
    }

    /** Happy-path: sub-payload objek valid tetap tersimpan (201). */
    public function test_komoditi_valid_array_subpayload_succeeds(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/komoditi', [
            'nama_komoditi' => 'Selada',
            'kategori_tanaman' => 'Sayuran Daun',
            'lingkungan' => ['suhu_min' => 15, 'suhu_max' => 25],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('komoditi_tanaman', ['nama_komoditi' => 'Selada']);
    }

    // ── M-1: pesan exception internal tidak boleh bocor ke client ──

    public function test_send_test_email_hides_internal_exception(): void
    {
        $this->actingAs($this->admin);

        Mail::shouldReceive('raw')->andThrow(new \Exception('SECRET_SMTP_LEAK_XYZ host=internal'));

        $response = $this->postJson('/api/settings/test-email', [
            'email' => 'ops@agrisense.test',
        ]);

        $response->assertStatus(500);
        $this->assertStringNotContainsString('SECRET_SMTP_LEAK_XYZ', $response->getContent());
    }

    // ── M-3: node_id AiInsight harus scalar (string device_code ATAU int PK),
    //         tolak array/objek yang dulu memicu 500. Konsisten dgn getHistory. ──

    /** node_id bertipe array harus ditolak 422 (dulu crash 500). */
    public function test_ai_insight_rejects_array_node_id(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/ai-insight/generate', [
            'node_id' => ['array', 'bukan', 'string'],
            'time_range' => '24h',
        ]);

        $response->assertStatus(422);
    }

    /** node_id integer (PK device) tetap diterima — bukan 422. */
    public function test_ai_insight_accepts_integer_node_id(): void
    {
        $this->actingAs($this->admin);

        $device = Device::create(['device_code' => 'NODE-INT-01']);

        $response = $this->postJson('/api/ai-insight/generate', [
            'node_id' => $device->id, // integer PK
            'time_range' => '24h',
            'force_rule_based' => true,
        ]);

        $this->assertNotEquals(422, $response->getStatusCode());
    }

    // ── M-6: cap limit /readings dinaikkan agar tidak diam-diam memotong data ──

    /** Dengan >500 reading, limit=1500 harus kembalikan semuanya (cap lama 500). */
    public function test_readings_limit_returns_more_than_old_cap(): void
    {
        $this->actingAs($this->admin);

        $plot = LandPlot::create([
            'plot_code' => 'L-LIM',
            'plot_name' => 'Lahan Limit',
            'latitude' => -6.85,
            'longitude' => 107.92,
            'area_hectare' => 1.0,
        ]);
        $device = Device::create(['device_code' => 'NODE-LIM', 'plot_id' => $plot->id]);

        $base = now();
        $rows = [];
        for ($i = 0; $i < 505; $i++) {
            $rows[] = [
                'device_id' => $device->id,
                'plot_id' => $plot->id,
                'reading_time' => $base->copy()->subMinutes($i),
                'air_temperature_sensor' => 25,
                'air_humidity_sensor' => 70,
                'soil_temperature' => 24,
                'soil_moisture' => 50,
                'soil_ph' => 6.5,
                'co2_sensor' => 400,
                'soil_organic_carbon' => 1.5,
                'carbon_flux' => 0.2,
                'data_valid' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('iot_readings')->insert($chunk);
        }

        // Cap baru 1500 → semua 505 kembali (cap lama 500 akan memotong jadi 500).
        $all = $this->getJson('/api/readings?limit=1500');
        $all->assertOk();
        $this->assertCount(505, $all->json());

        // Limit kecil tetap dihormati.
        $small = $this->getJson('/api/readings?limit=10');
        $this->assertCount(10, $small->json());
    }
}
