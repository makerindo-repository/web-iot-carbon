<?php

namespace Tests\Feature;

use App\Models\Garden;
use App\Models\Planting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullCrudSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** @test */
    public function full_hierarchy_creation_and_cascade_deletion(): void
    {
        // 1. Create Komoditi
        $komoditiRes = $this->actingAs($this->admin)->postJson('/api/komoditi', [
            'nama_komoditi' => 'Sync Test Komoditi',
            'kategori_tanaman' => 'Pangan',
        ]);
        $komoditiRes->assertStatus(201);
        $komoditiId = $komoditiRes->json('id');

        // 2. Create Land Plot
        $landPlotRes = $this->actingAs($this->admin)->postJson('/api/land-plots', [
            'plot_name' => 'Lahan Induk',
            'latitude' => -6.9,
            'longitude' => 107.6,
            'area_hectare' => 10,
        ]);
        $landPlotRes->assertStatus(201);
        $landPlotId = $landPlotRes->json('id');

        // 3. Create Garden inside Land Plot
        $gardenRes = $this->actingAs($this->admin)->postJson('/api/gardens', [
            'garden_name' => 'Kebun Anak',
            'land_plot_id' => $landPlotId,
            'latitude' => -6.9,
            'longitude' => 107.6,
            'area_hectare' => 2,
        ]);
        $gardenRes->assertStatus(201);
        $gardenId = $gardenRes->json('id');

        // 4. Create Planting inside Garden
        $plantingRes = $this->actingAs($this->admin)->postJson('/api/plantings', [
            'nama_tanaman' => 'Tanaman Cucu',
            'garden_id' => $gardenId,
            'komoditi_id' => $komoditiId,
            'status_fase' => 'Persiapan',
        ]);
        $plantingRes->assertStatus(201);
        $plantingId = $plantingRes->json('data.id');

        // Verify all exist in database
        $this->assertDatabaseHas('komoditi_tanaman', ['id' => $komoditiId]);
        $this->assertDatabaseHas('land_plots', ['id' => $landPlotId]);
        $this->assertDatabaseHas('gardens', ['id' => $gardenId]);
        $this->assertDatabaseHas('plantings', ['id' => $plantingId]);

        // 5. Delete Land Plot -> Should cascade and delete Garden and Planting
        // The API returns status 200 on success
        $deleteRes = $this->actingAs($this->admin)->deleteJson("/api/land-plots/{$landPlotId}");
        $deleteRes->assertStatus(200);

        // Verify Cascade
        $this->assertDatabaseMissing('land_plots', ['id' => $landPlotId]);
        $this->assertDatabaseMissing('gardens', ['id' => $gardenId]);
        $this->assertDatabaseMissing('plantings', ['id' => $plantingId]);

        // Verify Komoditi is NOT deleted (it's independent master data)
        $this->assertDatabaseHas('komoditi_tanaman', ['id' => $komoditiId]);
    }
}
