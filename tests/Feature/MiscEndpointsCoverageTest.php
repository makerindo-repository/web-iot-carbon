<?php

namespace Tests\Feature;

use App\Models\AboutCard;
use App\Models\CciAnalytic;
use App\Models\Device;
use App\Models\IotReading;
use App\Models\KomoditiTanaman;
use App\Models\LandPlot;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for endpoints not exercised by existing test suites:
 * DashboardController (already covered elsewhere), ReportController,
 * ModelPerformanceController, AboutCardController, CciController,
 * ForecastController, BmkgController, SystemController (settings/profile/logs),
 * PlantController, KomoditiController::categories, IotReadingController::getReadings.
 *
 * Roles used across the app: admin, operator, viewer (see SystemController::createUser).
 */
class MiscEndpointsCoverageTest extends TestCase
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

    private function makeDevice(array $overrides = []): Device
    {
        $plot = LandPlot::create([
            'plot_code' => 'L-MISC-'.uniqid(),
            'plot_name' => 'Lahan Misc',
            'latitude' => -6.85,
            'longitude' => 107.92,
            'area_hectare' => 1.0,
        ]);

        return Device::create(array_merge([
            'device_code' => 'NODE-MISC-'.uniqid(),
            'latitude' => -6.85,
            'longitude' => 107.92,
            'plot_id' => $plot->id,
        ], $overrides));
    }

    private function makeReading(Device $device, array $overrides = []): IotReading
    {
        return IotReading::create(array_merge([
            'device_id' => $device->id,
            'plot_id' => $device->plot_id,
            'reading_time' => now(),
            'air_temperature_sensor' => 27,
            'air_humidity_sensor' => 60,
            'soil_temperature' => 26,
            'soil_moisture' => 40,
            'soil_ph' => 6.5,
            'co2_sensor' => 450,
            'soil_organic_carbon' => 0,
            'carbon_flux' => 0,
        ], $overrides));
    }

    // ────────────────────────────────────────────────────────────
    // PlantController@index
    // ────────────────────────────────────────────────────────────

    public function test_plants_index_requires_auth(): void
    {
        $this->getJson('/api/plants')->assertStatus(401);
    }

    public function test_plants_index_returns_list_for_any_role(): void
    {
        Plant::create(['category' => 'Sayuran', 'name' => 'Cabai']);
        $this->actingAs($this->viewer);
        $response = $this->getJson('/api/plants');
        $response->assertOk();
        $response->assertJsonCount(1);
    }

    // ────────────────────────────────────────────────────────────
    // KomoditiController@categories
    // ────────────────────────────────────────────────────────────

    public function test_komoditi_categories_requires_auth(): void
    {
        $this->getJson('/api/komoditi/categories')->assertStatus(401);
    }

    public function test_komoditi_categories_returns_distinct_list(): void
    {
        KomoditiTanaman::create(['kode_komoditi' => 'K1', 'nama_komoditi' => 'Cabai', 'kategori_tanaman' => 'Sayuran']);
        KomoditiTanaman::create(['kode_komoditi' => 'K2', 'nama_komoditi' => 'Tomat', 'kategori_tanaman' => 'Sayuran']);
        KomoditiTanaman::create(['kode_komoditi' => 'K3', 'nama_komoditi' => 'Padi', 'kategori_tanaman' => 'Serealia']);

        $this->actingAs($this->viewer);
        $response = $this->getJson('/api/komoditi/categories');
        $response->assertOk();
        $response->assertJsonCount(2);
    }

    // ────────────────────────────────────────────────────────────
    // CciController@getCci
    // ────────────────────────────────────────────────────────────

    public function test_cci_requires_auth(): void
    {
        $this->getJson('/api/cci')->assertStatus(401);
    }

    public function test_cci_returns_summary_for_authenticated_user(): void
    {
        $device = $this->makeDevice();
        $reading = $this->makeReading($device, ['co2_sensor' => 500]);
        CciAnalytic::create([
            'iot_reading_id' => $reading->id,
            'device_id' => (string) $device->id,
            'cci_value' => 1.25,
            'cci_status' => 'sedang',
        ]);

        $this->actingAs($this->viewer);
        $response = $this->getJson('/api/cci');
        $response->assertOk();
        $response->assertJsonStructure(['data', 'summary' => ['avg_cci', 'max_cci', 'min_cci', 'total']]);
        $response->assertJsonPath('summary.total', 1);
    }

    public function test_cci_empty_dataset_does_not_500(): void
    {
        $this->actingAs($this->viewer);
        $response = $this->getJson('/api/cci');
        $response->assertOk();
        $response->assertJsonPath('summary.total', 0);
    }

    // ────────────────────────────────────────────────────────────
    // ForecastController
    // ────────────────────────────────────────────────────────────

    public function test_forecasts_index_requires_auth(): void
    {
        $this->getJson('/api/forecasts')->assertStatus(401);
    }

    public function test_forecasts_index_empty_returns_success_shape(): void
    {
        $this->actingAs($this->viewer);
        $response = $this->getJson('/api/forecasts');
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('count', 0);
    }

    public function test_forecasts_index_unknown_device_id_returns_empty_not_error(): void
    {
        $this->actingAs($this->viewer);
        $response = $this->getJson('/api/forecasts?device_id=DOES-NOT-EXIST');
        $response->assertOk();
        $response->assertJsonPath('count', 0);
    }

    public function test_forecasts_latest_requires_auth(): void
    {
        $this->getJson('/api/forecasts/latest')->assertStatus(401);
    }

    public function test_forecasts_latest_empty_ok(): void
    {
        $this->actingAs($this->viewer);
        $response = $this->getJson('/api/forecasts/latest');
        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // ────────────────────────────────────────────────────────────
    // ModelPerformanceController (admin,operator only)
    // ────────────────────────────────────────────────────────────

    public function test_model_performance_requires_auth(): void
    {
        $this->getJson('/api/model-performance')->assertStatus(401);
    }

    public function test_model_performance_viewer_forbidden(): void
    {
        $this->actingAs($this->viewer);
        $this->getJson('/api/model-performance')->assertStatus(403);
    }

    public function test_model_performance_operator_allowed(): void
    {
        $this->actingAs($this->operator);
        $response = $this->getJson('/api/model-performance');
        // Bundle files likely absent in test env -> controller returns 200 with success=false
        $response->assertOk();
        $response->assertJsonStructure(['success']);
    }

    public function test_model_performance_per_node_unknown_device_404(): void
    {
        $this->actingAs($this->operator);
        $response = $this->getJson('/api/model-performance/node/UNKNOWN-DEVICE');
        $response->assertStatus(404);
    }

    public function test_model_performance_per_node_viewer_forbidden(): void
    {
        $this->actingAs($this->viewer);
        $this->getJson('/api/model-performance/node/ANY')->assertStatus(403);
    }

    // ────────────────────────────────────────────────────────────
    // AboutCardController
    // ────────────────────────────────────────────────────────────

    public function test_about_cards_index_requires_auth(): void
    {
        $this->getJson('/api/about-cards')->assertStatus(401);
    }

    public function test_about_cards_index_only_active_visible(): void
    {
        AboutCard::create(['type' => 'team', 'title' => 'Active', 'is_active' => true]);
        AboutCard::create(['type' => 'team', 'title' => 'Inactive', 'is_active' => false]);

        $this->actingAs($this->viewer);
        $response = $this->getJson('/api/about-cards');
        $response->assertOk();
        $response->assertJsonCount(1);
    }

    public function test_about_cards_admin_index_requires_admin_or_operator(): void
    {
        $this->actingAs($this->viewer);
        $this->getJson('/api/about-cards/admin')->assertStatus(403);
    }

    public function test_about_cards_admin_index_shows_inactive_too(): void
    {
        AboutCard::create(['type' => 'team', 'title' => 'Active', 'is_active' => true]);
        AboutCard::create(['type' => 'team', 'title' => 'Inactive', 'is_active' => false]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/about-cards/admin');
        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_about_cards_store_requires_admin_or_operator(): void
    {
        $this->actingAs($this->viewer);
        $this->postJson('/api/about-cards', ['type' => 'team', 'title' => 'X'])->assertStatus(403);
    }

    public function test_about_cards_store_validates_type(): void
    {
        $this->actingAs($this->operator);
        $response = $this->postJson('/api/about-cards', ['type' => 'invalid-type', 'title' => 'X']);
        $response->assertStatus(422);
    }

    public function test_about_cards_full_crud_lifecycle(): void
    {
        $this->actingAs($this->admin);

        $create = $this->postJson('/api/about-cards', [
            'type' => 'feature',
            'title' => 'Fitur Baru',
            'sort_order' => 1,
        ]);
        $create->assertStatus(201);
        $id = $create->json('id');
        $this->assertDatabaseHas('about_cards', ['id' => $id, 'title' => 'Fitur Baru']);

        $update = $this->putJson('/api/about-cards/'.$id, ['title' => 'Fitur Diperbarui']);
        $update->assertOk();
        $this->assertDatabaseHas('about_cards', ['id' => $id, 'title' => 'Fitur Diperbarui']);

        $delete = $this->deleteJson('/api/about-cards/'.$id);
        $delete->assertOk();
        $this->assertDatabaseMissing('about_cards', ['id' => $id]);
    }

    public function test_about_cards_update_nonexistent_404(): void
    {
        $this->actingAs($this->admin);
        $this->putJson('/api/about-cards/999999', ['title' => 'X'])->assertStatus(404);
    }

    public function test_about_cards_destroy_nonexistent_404(): void
    {
        $this->actingAs($this->admin);
        $this->deleteJson('/api/about-cards/999999')->assertStatus(404);
    }

    // ────────────────────────────────────────────────────────────
    // ReportController@exportReport (admin,operator only)
    // ────────────────────────────────────────────────────────────

    public function test_reports_export_requires_auth(): void
    {
        $this->getJson('/api/reports/export')->assertStatus(401);
    }

    public function test_reports_export_viewer_forbidden(): void
    {
        $this->actingAs($this->viewer);
        $this->getJson('/api/reports/export')->assertStatus(403);
    }

    public function test_reports_export_raw_data_requires_dates(): void
    {
        $this->actingAs($this->operator);
        $response = $this->getJson('/api/reports/export?type=raw-data&format=json');
        $response->assertStatus(422);
    }

    public function test_reports_export_raw_data_json_happy_path(): void
    {
        $device = $this->makeDevice();
        $this->makeReading($device);

        $this->actingAs($this->operator);
        $response = $this->getJson('/api/reports/export?type=raw-data&format=json&start_date=2020-01-01&end_date=2030-01-01');
        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('count', 1);
    }

    public function test_reports_export_csv_stream(): void
    {
        $device = $this->makeDevice();
        $this->makeReading($device);

        $this->actingAs($this->operator);
        $response = $this->get('/api/reports/export?type=raw-data&format=csv&start_date=2020-01-01&end_date=2030-01-01');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_reports_export_maintenance_type_no_dates_needed(): void
    {
        $this->makeDevice();
        $this->actingAs($this->operator);
        $response = $this->getJson('/api/reports/export?type=maintenance&format=json');
        $response->assertOk();
        $response->assertJsonPath('type', 'maintenance');
    }

    public function test_reports_export_system_logs_type(): void
    {
        $this->actingAs($this->operator);
        $response = $this->getJson('/api/reports/export?type=system-logs&format=json');
        $response->assertOk();
        $response->assertJsonPath('type', 'system-logs');
    }

    public function test_reports_export_end_before_start_rejected(): void
    {
        $this->actingAs($this->operator);
        $response = $this->getJson('/api/reports/export?type=raw-data&format=json&start_date=2030-01-01&end_date=2020-01-01');
        $response->assertStatus(422);
    }

    // ────────────────────────────────────────────────────────────
    // SystemController: settings, profile, logs
    // ────────────────────────────────────────────────────────────

    public function test_settings_get_requires_auth(): void
    {
        $this->getJson('/api/settings')->assertStatus(401);
    }

    public function test_settings_get_hides_ai_engine_key_for_non_admin(): void
    {
        $this->actingAs($this->viewer);
        $response = $this->getJson('/api/settings');
        $response->assertOk();
        $response->assertJsonPath('aiEngineKey', '');
    }

    public function test_settings_get_returns_defaults(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/settings');
        $response->assertOk();
        $response->assertJsonStructure(['appName', 'co2Threshold', 'tempMax', 'humidityMin']);
    }

    public function test_settings_update_requires_admin(): void
    {
        $this->actingAs($this->operator);
        $this->postJson('/api/settings', ['appName' => 'Nope'])->assertStatus(403);
    }

    public function test_settings_update_happy_path(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/settings', ['appName' => 'AgriSense Test', 'tempMax' => '38']);
        $response->assertOk();
        $this->assertDatabaseHas('agrisense_settings', ['key' => 'appName', 'value' => 'AgriSense Test']);
    }

    public function test_settings_update_ignores_unknown_keys(): void
    {
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/settings', ['not_a_real_setting' => 'malicious']);
        $response->assertOk();
        $this->assertDatabaseMissing('agrisense_settings', ['key' => 'not_a_real_setting']);
    }

    public function test_profile_update_requires_auth(): void
    {
        $this->putJson('/api/profile', ['name' => 'X'])->assertStatus(401);
    }

    public function test_profile_update_happy_path_any_role(): void
    {
        $this->actingAs($this->viewer);
        $response = $this->putJson('/api/profile', ['name' => 'Viewer Baru']);
        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $this->viewer->id, 'name' => 'Viewer Baru']);
    }

    public function test_profile_update_requires_name(): void
    {
        $this->actingAs($this->viewer);
        $this->putJson('/api/profile', [])->assertStatus(422);
    }

    public function test_profile_password_requires_auth(): void
    {
        $this->putJson('/api/profile/password', [])->assertStatus(401);
    }

    public function test_profile_password_wrong_current_password(): void
    {
        $user = User::factory()->create(['role' => 'viewer', 'password' => bcrypt('correct-password')]);
        $this->actingAs($user);
        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'wrong-password',
            'new_password' => 'brand-new-pass',
            'new_password_confirmation' => 'brand-new-pass',
        ]);
        $response->assertStatus(422);
    }

    public function test_profile_password_happy_path(): void
    {
        $user = User::factory()->create(['role' => 'viewer', 'password' => bcrypt('correct-password')]);
        $this->actingAs($user);
        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'correct-password',
            'new_password' => 'brand-new-pass',
            'new_password_confirmation' => 'brand-new-pass',
        ]);
        $response->assertOk();
    }

    public function test_profile_password_requires_confirmation_match(): void
    {
        $user = User::factory()->create(['role' => 'viewer', 'password' => bcrypt('correct-password')]);
        $this->actingAs($user);
        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'correct-password',
            'new_password' => 'brand-new-pass',
            'new_password_confirmation' => 'mismatch',
        ]);
        $response->assertStatus(422);
    }

    public function test_logs_requires_admin_or_operator(): void
    {
        $this->actingAs($this->viewer);
        $this->getJson('/api/logs')->assertStatus(403);
    }

    public function test_logs_unauthenticated_401(): void
    {
        $this->getJson('/api/logs')->assertStatus(401);
    }

    public function test_logs_record_happy_path(): void
    {
        $this->actingAs($this->operator);
        $response = $this->postJson('/api/logs/record', ['action' => 'Melakukan sesuatu', 'module' => 'Node']);
        $response->assertOk();
        $response->assertJsonPath('status', 'success');
    }

    public function test_logs_record_invalid_module_rejected(): void
    {
        $this->actingAs($this->operator);
        $response = $this->postJson('/api/logs/record', ['action' => 'x', 'module' => 'NotARealModule']);
        $response->assertStatus(422);
    }

    public function test_logs_record_viewer_forbidden(): void
    {
        $this->actingAs($this->viewer);
        $this->postJson('/api/logs/record', ['action' => 'x'])->assertStatus(403);
    }

    // ────────────────────────────────────────────────────────────
    // BmkgController
    // ────────────────────────────────────────────────────────────

    public function test_bmkg_get_requires_auth(): void
    {
        $this->getJson('/api/bmkg')->assertStatus(401);
    }

    public function test_bmkg_get_empty_db_returns_mock_data(): void
    {
        $this->actingAs($this->viewer);
        $response = $this->getJson('/api/bmkg');
        $response->assertOk();
        $response->assertJsonPath('status', 'success');
    }

    /**
     * After the B-1 fix, BmkgController::sync() enforces the X-Cron-Token check
     * for every HTTP-originated request (guard `$request !== null`), so an
     * unauthenticated sync call over HTTP is now correctly rejected with 401 —
     * even under `php artisan test`. Only direct console invocation (the hourly
     * scheduler / Artisan, which passes no Request object) bypasses the token,
     * which is the intended cron entrypoint.
     */
    public function test_bmkg_internal_sync_without_token_is_rejected_with_401(): void
    {
        config(['services.cron.sync_token' => 'test-secret-token']);
        $response = $this->getJson('/api/internal/bmkg/sync');
        // Token dikonfigurasi tapi tidak disertakan di request → 401 (bukan lagi
        // 404 console-bypass seperti sebelum fix B-1).
        $response->assertStatus(401);
    }

    public function test_bmkg_internal_sync_no_plots_with_valid_token(): void
    {
        config(['services.cron.sync_token' => 'test-secret-token']);
        $response = $this->getJson('/api/internal/bmkg/sync', ['X-Cron-Token' => 'test-secret-token']);
        // No LandPlot rows in a fresh in-memory DB -> 404 per controller logic
        $response->assertStatus(404);
    }

    // ────────────────────────────────────────────────────────────
    // IotReadingController@getReadings — XSS / SQLi style payload safety
    // ────────────────────────────────────────────────────────────

    public function test_readings_device_id_filter_with_sqli_style_string_is_safe(): void
    {
        $this->makeDevice(['device_code' => 'NODE-SAFE']);
        $this->actingAs($this->viewer);

        $response = $this->getJson("/api/readings?device_id=' OR '1'='1");
        $response->assertOk();
        $response->assertJsonCount(0); // no match, no SQL error, no data leak
    }

    public function test_readings_requires_auth(): void
    {
        $this->getJson('/api/readings')->assertStatus(401);
    }

    // ────────────────────────────────────────────────────────────
    // Method-not-allowed checks for a couple of representative routes
    // ────────────────────────────────────────────────────────────

    public function test_login_route_rejects_get_method(): void
    {
        $this->actingAs($this->admin);
        $response = $this->get('/api/login');
        $response->assertStatus(405);
    }

    public function test_nodes_route_rejects_patch_method(): void
    {
        $this->actingAs($this->admin);
        // /api/nodes only supports GET (index) and POST (store); PATCH is undefined on the collection route
        $response = $this->patchJson('/api/nodes');
        $response->assertStatus(405);
    }

    public function test_garden_activity_log_rejects_put_method(): void
    {
        // Setelah fix: route PUT/PATCH garden-activity-logs dihapus karena log
        // aktivitas bersifat immutable (controller tidak punya method update()).
        // URI {id} hanya mendukung DELETE → PUT balas 405, BUKAN lagi 500.
        $this->actingAs($this->admin);
        $response = $this->putJson('/api/garden-activity-logs/1');
        $response->assertStatus(405);
    }
}
