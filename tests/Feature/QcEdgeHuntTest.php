<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Garden;
use App\Models\LandPlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QC Edge Hunt Test Suite — 8 Vektor Attack
 * Target: Dashboard, Data Sensor, Lahan, Kebun, Tanaman, Perangkat (Node)
 */
class QcEdgeHuntTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 📊 DASHBOARD
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** V2: Dashboard with zero data — should return clean JSON, not crash */
    public function test_dashboard_summary_empty_database(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/dashboard/summary');
        $response->assertOk();
        $response->assertJsonPath('nodes.total', 0);
        $response->assertJsonPath('nodes.online', 0);
    }

    /** V6: Dashboard with large dataset — should not timeout */
    public function test_dashboard_summary_with_devices(): void
    {
        $this->actingAs($this->admin);
        for ($i = 0; $i < 5; $i++) {
            Device::create([
                'device_code' => "ON-$i",
                'firmware_version' => '1.0.0',
            ]);
            // Force status to online (since it's not fillable)
            Device::where('device_code', "ON-$i")->update(['device_status' => 'online']);
        }
        for ($i = 0; $i < 3; $i++) {
            Device::create([
                'device_code' => "OFF-$i",
                'firmware_version' => '1.0.0',
            ]);
            Device::where('device_code', "OFF-$i")->update(['device_status' => 'offline']);
        }

        $response = $this->getJson('/api/dashboard/summary');
        $response->assertOk();
        $response->assertJsonPath('nodes.total', 8);
        $response->assertJsonPath('nodes.online', 5);
        $response->assertJsonPath('nodes.offline', 3);
    }

    /** V5: Dashboard access without auth — should be blocked */
    public function test_dashboard_requires_auth(): void
    {
        $response = $this->getJson('/api/dashboard/summary');
        $response->assertUnauthorized();
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 📡 DATA SENSOR (IoT Readings)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** V1: Boundary — latitude out of range */
    public function test_iot_reading_latitude_boundary(): void
    {
        Device::create(['device_code' => 'NODE-B1', 'firmware_version' => '1.0.0']);

        $response = $this->postJson('/api/iot/agrisense/readings', [
            'device_id' => 'NODE-B1',
            'location' => ['latitude' => 91, 'longitude' => 107.5],
            'carbon_data' => ['co2_ppm' => 400],
        ]);
        $response->assertStatus(422);
    }

    /** V2: Null strike — missing required location */
    public function test_iot_reading_missing_location(): void
    {
        Device::create(['device_code' => 'NODE-N1', 'firmware_version' => '1.0.0']);

        $response = $this->postJson('/api/iot/agrisense/readings', [
            'device_id' => 'NODE-N1',
            'carbon_data' => ['co2_ppm' => 400],
        ]);
        $response->assertStatus(422);
    }

    /** V3: Type confusion — string as co2_ppm */
    public function test_iot_reading_type_confusion(): void
    {
        Device::create(['device_code' => 'NODE-T1', 'firmware_version' => '1.0.0']);

        $response = $this->postJson('/api/iot/agrisense/readings', [
            'device_id' => 'NODE-T1',
            'location' => ['latitude' => -6.5, 'longitude' => 107.5],
            'carbon_data' => ['co2_ppm' => 'bukan-angka'],
        ]);
        $response->assertStatus(422);
    }

    /** V2: Unregistered device — should be rejected 403 */
    public function test_iot_reading_unregistered_device(): void
    {
        $response = $this->postJson('/api/iot/agrisense/readings', [
            'device_id' => 'GHOST-DEVICE-999',
            'location' => ['latitude' => -6.5, 'longitude' => 107.5],
            'carbon_data' => ['co2_ppm' => 400],
        ]);
        $response->assertStatus(403);
    }

    /** V1: Valid full payload with new sensor metrics — must succeed */
    public function test_iot_reading_valid_full_payload(): void
    {
        $landPlot = LandPlot::create([
            'plot_code' => 'L-TEST',
            'plot_name' => 'Lahan Test',
            'latitude' => -6.85,
            'longitude' => 107.92,
            'area_hectare' => 1.5,
        ]);
        Device::create([
            'device_code' => 'NODE-FULL',
            'firmware_version' => '1.0.0',
            'plot_id' => $landPlot->id,
        ]);

        $response = $this->postJson('/api/iot/agrisense/readings', [
            'device_id' => 'NODE-FULL',
            'location' => ['latitude' => -6.84, 'longitude' => 107.90, 'altitude_m' => 483],
            'carbon_data' => [
                'co2_ppm' => 430.46,
                'tvoc_ppb' => 73.43,
                'ch4_ppm' => 1.78,
                'no2_ppb' => 12.77,
                'n2o_ppb' => 330.75,
            ],
            'environment' => [
                'air_temperature_c' => 25.94,
                'air_humidity_percent' => 71.98,
                'air_pressure_hpa' => 1008.01,
                'wind_speed_kmh' => 8.11,
                'light_lux' => 38865,
            ],
            'soil_7in1' => [
                'soil_moisture_percent' => 41.00,
                'soil_temperature_c' => 25.90,
                'soil_ec_ms_cm' => 1.32,
                'soil_ph' => 6.64,
                'soil_n_mg_kg' => 169,
                'soil_p_mg_kg' => 41,
                'soil_k_mg_kg' => 224,
            ],
            'power' => ['battery_voltage' => 12.60, 'battery_percent' => 48],
            'communication' => ['network_type' => 'WiFi', 'rssi_dbm' => -48],
            'status' => ['node_status' => 'online', 'ip' => '192.168.1.46'],
        ]);
        $response->assertStatus(201);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🌍 LAHAN (Land Plots)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** V5: Viewer role should NOT be able to create land plots */
    public function test_land_plot_viewer_cannot_create(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer);

        $response = $this->postJson('/api/land-plots', [
            'plot_name' => 'Lahan Infiltrasi',
        ]);
        // Viewer harus ditolak (403 dari role middleware)
        $this->assertContains($response->getStatusCode(), [403, 401]);
    }

    /** V1: Boundary — negative area */
    public function test_land_plot_negative_area(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/land-plots', [
            'plot_name' => 'Lahan Error',
            'area_hectare' => -50,
        ]);
        $response->assertStatus(422);
    }

    /** V7: Encoding — XSS in plot name */
    public function test_land_plot_xss_in_name(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/land-plots', [
            'plot_name' => '<script>alert("xss")</script>',
        ]);
        // Should create but value is stored as-is (frontend escapes)
        // At minimum, it should not crash (500)
        // 🔴 FINDING: This currently crashes with 500 due to NOT NULL on latitude
        $this->assertContains($response->getStatusCode(), [201, 422]);
    }

    /** V2: Null — create with only required field */
    public function test_land_plot_minimal_payload(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/land-plots', [
            'plot_name' => 'Lahan Minimal',
            'latitude' => -6.85,
            'longitude' => 107.92,
            'area_hectare' => 1.0,
        ]);
        $response->assertStatus(201);
    }

    /** V2: Show non-existent land plot */
    public function test_land_plot_show_nonexistent(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/land-plots/99999');
        $response->assertStatus(404);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🌿 KEBUN (Gardens)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** V2: Garden without required land_plot_id */
    public function test_garden_missing_land_plot_id(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/gardens', [
            'garden_name' => 'Kebun Tanpa Lahan',
        ]);
        $response->assertStatus(422);
    }

    /** V2: Garden with non-existent land_plot_id */
    public function test_garden_nonexistent_land_plot(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/gardens', [
            'garden_name' => 'Kebun Hantu',
            'land_plot_id' => 99999,
        ]);
        $response->assertStatus(422);
    }

    /** V1: Garden valid creation */
    public function test_garden_valid_creation(): void
    {
        $this->actingAs($this->admin);
        $landPlot = LandPlot::create([
            'plot_code' => 'L-G01',
            'plot_name' => 'Lahan Utama',
            'latitude' => -6.85,
            'longitude' => 107.92,
            'area_hectare' => 1.5,
        ]);

        $response = $this->postJson('/api/gardens', [
            'land_plot_id' => $landPlot->id,
            'garden_name' => 'Kebun Baru',
            'area_hectare' => 2.5,
            'latitude' => -6.85,
            'longitude' => 107.92,
        ]);
        $response->assertStatus(201);
    }

    /** V1: Garden boundary — area terlalu besar */
    public function test_garden_oversized_area(): void
    {
        $this->actingAs($this->admin);
        $landPlot = LandPlot::create([
            'plot_code' => 'L-G02',
            'plot_name' => 'Lahan 2',
            'latitude' => -6.85,
            'longitude' => 107.92,
            'area_hectare' => 1.5,
        ]);

        $response = $this->postJson('/api/gardens', [
            'land_plot_id' => $landPlot->id,
            'garden_name' => 'Kebun Raksasa',
            'area_hectare' => 99999999999.99,
            'latitude' => -6.85,
            'longitude' => 107.92,
        ]);
        $response->assertStatus(422);
    }

    /** V4: Garden with invalid kondisi_sekitar enum */
    public function test_garden_invalid_kondisi_sekitar(): void
    {
        $this->actingAs($this->admin);
        $landPlot = LandPlot::create([
            'plot_code' => 'L-G03',
            'plot_name' => 'Lahan 3',
            'latitude' => -6.85,
            'longitude' => 107.92,
            'area_hectare' => 1.5,
        ]);

        $response = $this->postJson('/api/gardens', [
            'land_plot_id' => $landPlot->id,
            'garden_name' => 'Kebun Enum',
            'kondisi_sekitar' => 'planet_mars',
            'latitude' => -6.85,
            'longitude' => 107.92,
        ]);
        $response->assertStatus(422);
    }

    /** V2: Show non-existent garden */
    public function test_garden_show_nonexistent(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/gardens/99999');
        $response->assertStatus(404);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🌱 TANAMAN (Plantings)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** V2: Planting without required fields */
    public function test_planting_missing_required(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/plantings', []);
        $response->assertStatus(422);
    }

    /** V2: Planting with non-existent garden_id */
    public function test_planting_nonexistent_garden(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/plantings', [
            'nama_tanaman' => 'Cabai Merah',
            'garden_id' => 99999,
            'komoditi_id' => 99999,
            'status_fase' => 'vegetatif',
        ]);
        $response->assertStatus(422);
    }

    /** V2: Show non-existent planting */
    public function test_planting_show_nonexistent(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/plantings/99999');
        $response->assertStatus(404);
    }

    /** V2: Delete non-existent planting */
    public function test_planting_delete_nonexistent(): void
    {
        $this->actingAs($this->admin);
        $response = $this->deleteJson('/api/plantings/99999');
        $response->assertStatus(404);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 📡 PERANGKAT (Nodes)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** V2: Create node without required device code */
    public function test_node_missing_device_code(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/nodes', []);
        $response->assertStatus(422);
    }

    /** V4: Create node with duplicate device code */
    public function test_node_duplicate_device_code(): void
    {
        $this->actingAs($this->admin);
        Device::create(['device_code' => 'NODE-DUP', 'firmware_version' => '1.0.0']);

        $response = $this->postJson('/api/nodes', ['id' => 'NODE-DUP']);
        $response->assertStatus(422);
    }

    /** V1: Create node — valid payload */
    public function test_node_valid_creation(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/nodes', [
            'id' => 'NODE-NEW-001',
            'latitude' => -6.85,
            'longitude' => 107.92,
        ]);
        $response->assertStatus(201);
    }

    /** V2: Show non-existent node */
    public function test_node_show_nonexistent(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/nodes/GHOST-NODE-999');
        $response->assertStatus(404);
    }

    /** V2: Delete non-existent node */
    public function test_node_delete_nonexistent(): void
    {
        $this->actingAs($this->admin);
        $response = $this->deleteJson('/api/nodes/GHOST-NODE-999');
        $response->assertStatus(404);
    }

    /** V7: Node with unicode characters in device code */
    public function test_node_unicode_device_code(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/nodes', [
            'id' => 'NODE-🔥-émojî',
        ]);
        // Should not crash (500)
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    /** V3: Node update with invalid lahanId type */
    public function test_node_update_invalid_lahan_type(): void
    {
        $this->actingAs($this->admin);
        Device::create(['device_code' => 'NODE-TYPE', 'firmware_version' => '1.0.0']);

        $response = $this->putJson('/api/nodes/NODE-TYPE', [
            'lahanId' => 'bukan-angka',
        ]);
        $response->assertStatus(422);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🔐 AUTH & ROLE ACCESS
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** V5: Viewer role trying to create nodes — should be blocked */
    public function test_viewer_cannot_create_node(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer);

        $response = $this->postJson('/api/nodes', ['id' => 'NODE-HACK']);
        $this->assertContains($response->getStatusCode(), [403, 401]);
    }

    /** V5: Viewer role trying to delete garden — should be blocked */
    public function test_viewer_cannot_delete_garden(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer);

        $response = $this->deleteJson('/api/gardens/1');
        $this->assertContains($response->getStatusCode(), [403, 401, 404]);
    }

    /** V5: Unauthenticated access to protected endpoints */
    public function test_unauthenticated_access_blocked(): void
    {
        $response = $this->getJson('/api/nodes');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/land-plots');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/gardens');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/plantings');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/readings');
        $response->assertUnauthorized();
    }
}
