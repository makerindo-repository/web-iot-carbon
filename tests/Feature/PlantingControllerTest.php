<?php

namespace Tests\Feature;

use App\Models\Garden;
use App\Models\KomoditiFaseTanam;
use App\Models\KomoditiTanaman;
use App\Models\LandPlot;
use App\Models\Planting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private User $viewer;

    private LandPlot $landPlot;

    private Garden $garden;

    private KomoditiTanaman $komoditi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->operator = User::factory()->create(['role' => 'operator']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);

        $this->landPlot = LandPlot::create([
            'plot_code' => 'LHN-TEST-001',
            'plot_name' => 'Lahan Test',
            'latitude' => -6.9,
            'longitude' => 107.6,
            'area_hectare' => 5.0,
        ]);

        $this->garden = Garden::create([
            'garden_code' => 'KBN-TEST-001',
            'garden_name' => 'Kebun Test',
            'land_plot_id' => $this->landPlot->id,
            'latitude' => -6.9,
            'longitude' => 107.6,
            'area_hectare' => 2.0,
        ]);

        $this->komoditi = KomoditiTanaman::create([
            'kode_komoditi' => 'KMD-TEST01',
            'nama_komoditi' => 'Tomat Test',
            'kategori_tanaman' => 'Sayuran',
            'status' => 'Aktif',
            'is_system' => false,
        ]);
    }

    // ── Happy Path Tests ──

    /** @test */
    public function admin_can_create_planting_with_valid_data(): void
    {
        $payload = [
            'nama_tanaman' => 'Tomat Musim Hujan',
            'garden_id' => $this->garden->id,
            'komoditi_id' => $this->komoditi->id,
            'tanggal_tanam' => '2026-07-01',
            'status_fase' => 'Persiapan',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/plantings', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.nama_tanaman', 'Tomat Musim Hujan');
        $this->assertDatabaseHas('plantings', ['nama_tanaman' => 'Tomat Musim Hujan']);
    }

    /** @test */
    public function operator_can_create_planting(): void
    {
        $payload = [
            'nama_tanaman' => 'Jagung Operator',
            'garden_id' => $this->garden->id,
            'status_fase' => 'Vegetatif',
        ];

        $response = $this->actingAs($this->operator)
            ->postJson('/api/plantings', $payload);

        $response->assertStatus(201);
    }

    /** @test */
    public function can_list_all_plantings(): void
    {
        Planting::create([
            'nama_tanaman' => 'Padi Test',
            'garden_id' => $this->garden->id,
            'status_fase' => 'Persiapan',
        ]);

        $response = $this->actingAs($this->viewer)
            ->getJson('/api/plantings');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    /** @test */
    public function can_show_single_planting(): void
    {
        $planting = Planting::create([
            'nama_tanaman' => 'Show Test',
            'garden_id' => $this->garden->id,
            'status_fase' => 'Generatif',
        ]);

        $response = $this->actingAs($this->viewer)
            ->getJson("/api/plantings/{$planting->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('nama_tanaman', 'Show Test');
    }

    /** @test */
    public function admin_can_update_planting(): void
    {
        $planting = Planting::create([
            'nama_tanaman' => 'Before Update',
            'garden_id' => $this->garden->id,
            'status_fase' => 'Persiapan',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/plantings/{$planting->id}", [
                'nama_tanaman' => 'After Update',
                'status_fase' => 'Panen',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.nama_tanaman', 'After Update');
        $response->assertJsonPath('data.status_fase', 'Panen');
    }

    /** @test */
    public function admin_can_delete_planting(): void
    {
        $planting = Planting::create([
            'nama_tanaman' => 'To Delete',
            'garden_id' => $this->garden->id,
            'status_fase' => 'Persiapan',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/plantings/{$planting->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('plantings', ['id' => $planting->id]);
    }

    // ── RBAC Tests ──

    /** @test */
    public function viewer_cannot_create_planting(): void
    {
        $response = $this->actingAs($this->viewer)
            ->postJson('/api/plantings', [
                'nama_tanaman' => 'Viewer Attempt',
                'garden_id' => $this->garden->id,
                'status_fase' => 'Persiapan',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function viewer_cannot_delete_planting(): void
    {
        $planting = Planting::create([
            'nama_tanaman' => 'Protected',
            'garden_id' => $this->garden->id,
            'status_fase' => 'Persiapan',
        ]);

        $response = $this->actingAs($this->viewer)
            ->deleteJson("/api/plantings/{$planting->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_cannot_access_plantings(): void
    {
        $this->getJson('/api/plantings')->assertStatus(401);
        $this->postJson('/api/plantings', [])->assertStatus(401);
    }

    // ── Validation Tests ──

    /** @test */
    public function create_fails_without_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/plantings', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nama_tanaman', 'garden_id', 'status_fase']);
    }

    /** @test */
    public function create_fails_with_nonexistent_garden(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/plantings', [
                'nama_tanaman' => 'Ghost Garden',
                'garden_id' => 99999,
                'status_fase' => 'Persiapan',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['garden_id']);
    }

    /** @test */
    public function create_allows_null_komoditi_id(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/plantings', [
                'nama_tanaman' => 'No Komoditi',
                'garden_id' => $this->garden->id,
                'komoditi_id' => null,
                'status_fase' => 'Persiapan',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.komoditi_id', null);
    }

    // ── XSS Sanitization Tests ──

    /** @test */
    public function store_sanitizes_xss_in_nama_tanaman(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/plantings', [
                'nama_tanaman' => '<script>alert("xss")</script>Tomat',
                'garden_id' => $this->garden->id,
                'status_fase' => 'Persiapan',
            ]);

        $response->assertStatus(201);
        $storedName = $response->json('data.nama_tanaman');
        $this->assertStringNotContainsString('<script>', $storedName);
        $this->assertStringContainsString('Tomat', $storedName);
    }

    /** @test */
    public function update_sanitizes_xss_in_status_fase(): void
    {
        $planting = Planting::create([
            'nama_tanaman' => 'XSS Test',
            'garden_id' => $this->garden->id,
            'status_fase' => 'Persiapan',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/plantings/{$planting->id}", [
                'status_fase' => '<img onerror=alert(1) src=x>Vegetatif',
            ]);

        $response->assertStatus(200);
        $storedFase = $response->json('data.status_fase');
        $this->assertStringNotContainsString('<img', $storedFase);
    }

    // ── Estimasi Panen Tests ──

    /** @test */
    public function store_calculates_estimasi_panen_from_komoditi_fase(): void
    {
        // Create fase tanam for komoditi — 90 hari
        KomoditiFaseTanam::create([
            'komoditi_id' => $this->komoditi->id,
            'usia_tanam_max' => 90,
            'satuan_usia' => 'hari',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/plantings', [
                'nama_tanaman' => 'Panen Calc Test',
                'garden_id' => $this->garden->id,
                'komoditi_id' => $this->komoditi->id,
                'tanggal_tanam' => '2026-07-01',
                'status_fase' => 'Persiapan',
            ]);

        $response->assertStatus(201);
        $estimasi = $response->json('data.estimasi_panen');
        $this->assertNotNull($estimasi);
        // 2026-07-01 + 90 days = 2026-09-29
        $this->assertStringStartsWith('2026-09-29', $estimasi);
    }

    /** @test */
    public function estimasi_panen_converts_weeks_to_days(): void
    {
        KomoditiFaseTanam::create([
            'komoditi_id' => $this->komoditi->id,
            'usia_tanam_max' => 12,
            'satuan_usia' => 'minggu',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/plantings', [
                'nama_tanaman' => 'Week Calc',
                'garden_id' => $this->garden->id,
                'komoditi_id' => $this->komoditi->id,
                'tanggal_tanam' => '2026-01-01',
                'status_fase' => 'Persiapan',
            ]);

        $response->assertStatus(201);
        $estimasi = $response->json('data.estimasi_panen');
        // 12 weeks × 7 = 84 days → 2026-01-01 + 84 = 2026-03-26
        $this->assertStringStartsWith('2026-03-26', $estimasi);
    }

    /** @test */
    public function estimasi_panen_converts_months_to_days(): void
    {
        KomoditiFaseTanam::create([
            'komoditi_id' => $this->komoditi->id,
            'usia_tanam_max' => 3,
            'satuan_usia' => 'bulan',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/plantings', [
                'nama_tanaman' => 'Month Calc',
                'garden_id' => $this->garden->id,
                'komoditi_id' => $this->komoditi->id,
                'tanggal_tanam' => '2026-01-01',
                'status_fase' => 'Persiapan',
            ]);

        $response->assertStatus(201);
        $estimasi = $response->json('data.estimasi_panen');
        // 3 months × 30 = 90 days → 2026-01-01 + 90 = 2026-04-01
        $this->assertStringStartsWith('2026-04-01', $estimasi);
    }

    // ── Edge Case: Show/Delete nonexistent ──

    /** @test */
    public function show_nonexistent_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/plantings/99999');

        $response->assertStatus(404);
    }

    /** @test */
    public function delete_nonexistent_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/plantings/99999');

        $response->assertStatus(404);
    }

    // ── Data Integrity: Cascade ──

    /** @test */
    public function deleting_garden_cascades_to_planting(): void
    {
        $planting = Planting::create([
            'nama_tanaman' => 'Cascade Test',
            'garden_id' => $this->garden->id,
            'status_fase' => 'Persiapan',
        ]);

        $this->garden->delete();

        $this->assertDatabaseMissing('plantings', ['id' => $planting->id]);
    }
}
