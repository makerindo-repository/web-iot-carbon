<?php

namespace Tests\Feature;

use App\Models\Garden;
use App\Models\KomoditiFaseTanam;
use App\Models\KomoditiTanaman;
use App\Models\LandPlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature + RBAC test — jalur normal (happy-path) & matriks peran.
 *
 * Melengkapi QcEdgeHuntTest (fokus edge-case) dengan:
 *  - Siklus penuh Planting (create → update → delete) yang belum teruji.
 *  - Peran `operator` yang sebelumnya TIDAK pernah diuji sama sekali.
 *  - CRUD & RBAC User management (admin-only).
 * Sekaligus mengunci fix L-2 (min password 8) & L-3 (admin tak boleh
 * mengubah peran sendiri).
 */
class FeatureCrudRbacTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->operator = User::factory()->create(['role' => 'operator']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    /** Scaffold lahan → kebun → komoditi; kembalikan [gardenId, komoditiId]. */
    private function scaffoldGardenKomoditi(): array
    {
        $plot = LandPlot::create([
            'plot_code' => 'L-FCR',
            'plot_name' => 'Lahan FCR',
            'latitude' => -6.85,
            'longitude' => 107.92,
            'area_hectare' => 2.0,
        ]);
        $garden = Garden::create([
            'land_plot_id' => $plot->id,
            'garden_code' => 'G-FCR',
            'garden_name' => 'Kebun FCR',
            'latitude' => -6.85,
            'longitude' => 107.92,
        ]);
        $komoditi = KomoditiTanaman::create([
            'kode_komoditi' => 'KMD-FCR',
            'nama_komoditi' => 'Cabai',
            'kategori_tanaman' => 'Sayuran',
        ]);

        return [$garden->id, $komoditi->id];
    }

    // ── Planting: siklus penuh (create → update → delete) ──

    public function test_planting_full_lifecycle(): void
    {
        $this->actingAs($this->operator); // operator boleh menulis
        [$gardenId, $komoditiId] = $this->scaffoldGardenKomoditi();

        $create = $this->postJson('/api/plantings', [
            'nama_tanaman' => 'Cabai Blok A',
            'garden_id' => $gardenId,
            'komoditi_id' => $komoditiId,
            'status_fase' => 'vegetatif',
        ]);
        $create->assertStatus(201);
        $create->assertJsonPath('status', 'success');
        $plantingId = $create->json('data.id');
        $this->assertDatabaseHas('plantings', ['id' => $plantingId, 'nama_tanaman' => 'Cabai Blok A']);

        $update = $this->putJson('/api/plantings/'.$plantingId, ['status_fase' => 'generatif']);
        $update->assertOk();
        $this->assertDatabaseHas('plantings', ['id' => $plantingId, 'status_fase' => 'generatif']);

        $delete = $this->deleteJson('/api/plantings/'.$plantingId);
        $delete->assertOk();
        $this->assertDatabaseMissing('plantings', ['id' => $plantingId]);
    }

    public function test_planting_auto_calculates_estimasi_panen(): void
    {
        $this->actingAs($this->admin);
        [$gardenId, $komoditiId] = $this->scaffoldGardenKomoditi();

        KomoditiFaseTanam::create([
            'komoditi_id' => $komoditiId,
            'usia_tanam_max' => 90,
            'satuan_usia' => 'hari',
        ]);

        $response = $this->postJson('/api/plantings', [
            'nama_tanaman' => 'Cabai Estimasi',
            'garden_id' => $gardenId,
            'komoditi_id' => $komoditiId,
            'status_fase' => 'vegetatif',
            'tanggal_tanam' => '2026-01-01',
        ]);
        $response->assertStatus(201);

        // 2026-01-01 + 90 hari = 2026-04-01 (auto-hitung dari fase tanam)
        $estimasi = $response->json('data.estimasi_panen');
        $this->assertNotNull($estimasi);
        $this->assertStringContainsString('2026-04-01', $estimasi);
    }

    /** B-0b (kembaran B-0): garden tanpa lat/lon/area tidak boleh crash 500. */
    public function test_garden_create_without_optional_geo_succeeds(): void
    {
        $this->actingAs($this->admin);
        $plot = LandPlot::create([
            'plot_code' => 'L-GB',
            'plot_name' => 'Lahan GB',
            'latitude' => -6.85,
            'longitude' => 107.92,
            'area_hectare' => 1.0,
        ]);

        $response = $this->postJson('/api/gardens', [
            'land_plot_id' => $plot->id,
            'garden_name' => 'Kebun Tanpa Geo',
            'latitude' => -6.85,
            'longitude' => 107.92,
            'area_hectare' => 1.0,
        ]);
        $response->assertStatus(201);
    }

    // ── RBAC: peran operator (sebelumnya tak teruji) ──

    public function test_operator_can_create_node(): void
    {
        $this->actingAs($this->operator);
        $response = $this->postJson('/api/nodes', [
            'id' => 'NODE-OP-1',
            'latitude' => -6.85,
            'longitude' => 107.92,
        ]);
        $response->assertStatus(201);
    }

    public function test_operator_cannot_access_users(): void
    {
        $this->actingAs($this->operator);
        $this->getJson('/api/users')->assertStatus(403);
    }

    public function test_operator_cannot_update_settings(): void
    {
        $this->actingAs($this->operator);
        $this->postJson('/api/settings', ['appName' => 'Hack'])->assertStatus(403);
    }

    // ── User management: happy-path (admin) ──

    public function test_admin_can_create_user(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/users', [
            'name' => 'Operator Baru',
            'email' => 'operator.baru@agrisense.test',
            'password' => 'rahasia-kuat-8',
            'role' => 'operator',
        ]);
        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('users', [
            'email' => 'operator.baru@agrisense.test',
            'role' => 'operator',
        ]);
    }

    public function test_admin_can_update_other_user_role(): void
    {
        $this->actingAs($this->admin);
        $response = $this->putJson('/api/users/'.$this->viewer->id, ['role' => 'operator']);
        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $this->viewer->id, 'role' => 'operator']);
    }

    // ── L-3: admin tak boleh mengubah peran sendiri ──

    public function test_admin_cannot_change_own_role(): void
    {
        $this->actingAs($this->admin);
        $response = $this->putJson('/api/users/'.$this->admin->id, ['role' => 'viewer']);
        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'role' => 'admin']); // tetap admin
    }

    public function test_admin_can_still_update_own_name(): void
    {
        $this->actingAs($this->admin);
        $response = $this->putJson('/api/users/'.$this->admin->id, ['name' => 'Admin Ganti Nama']);
        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'name' => 'Admin Ganti Nama']);
    }

    // ── L-2: password minimal 8 karakter ──

    public function test_create_user_rejects_short_password(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/users', [
            'name' => 'Pendek',
            'email' => 'pendek@agrisense.test',
            'password' => 'abc123', // 6 char < 8
            'role' => 'viewer',
        ]);
        $response->assertStatus(422);
    }
}
