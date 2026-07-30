<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdgeCaseAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_password_volume_assault()
    {
        // V6 Volume Assault: Send a 1MB string to the login endpoint
        $giantString = str_repeat('A', 1024 * 1024); // 1 MB string

        User::factory()->create([
            'email' => 'admin@agrisense.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@agrisense.com',
            'password' => $giantString,
        ]);

        // If the vulnerability exists, this might timeout, crash, or return 422 if we fixed it.
        // For now, we assert it doesn't crash the server (returns a valid HTTP status)
        // and ideally returns 422 Unprocessable Entity (if fixed).
        // Since it's NOT fixed yet, it might return 401 (if Hash::check processes it quickly) or 500.
        $this->assertNotEquals(500, $response->getStatusCode(), 'Volume assault caused 500 Error!');

        // The ideal fix is that it returns 422 because it exceeds max length
        $response->assertStatus(422);
    }

    public function test_land_plot_boundary_attack_negative_area()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        // V1 Boundary: negative area
        $response = $this->postJson('/api/land-plots', [
            'plot_name' => 'Kebun Error',
            'area_hectare' => -50,
        ]);

        // We expect a 422 error if validated, not 500 SQL truncate
        $response->assertStatus(422);
    }

    public function test_land_plot_boundary_attack_massive_area()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        // V1 Boundary: massive area exceeding DECIMAL(8,2)
        $response = $this->postJson('/api/land-plots', [
            'plot_name' => 'Kebun Error',
            'area_hectare' => 99999999999.99,
        ]);

        // We expect a 422 error if validated, not 500 SQL truncate
        $response->assertStatus(422);
    }

    public function test_iot_reading_float_overflow()
    {
        // Create a device manually
        $device = Device::create([
            'device_code' => 'NODE-TEST',
            'firmware_version' => '1.0.0',
        ]);

        // V1 Boundary: sending a giant scientific notation that could overflow DECIMAL/FLOAT
        $response = $this->postJson('/api/iot/agrisense/readings', [
            'device_id' => 'NODE-TEST',
            'location' => [
                'latitude' => -6.5,
                'longitude' => 107.5,
            ],
            'carbon_data' => [
                'co2_ppm' => 1.0E+300, // Giant float
            ],
        ]);

        // Expecting 422 if it's protected, or at least not 500.
        $this->assertNotEquals(500, $response->getStatusCode(), 'Float overflow caused 500 SQL Error!');
        $response->assertStatus(422);
    }

    public function test_iot_reading_new_metrics_boundary()
    {
        // Create a device manually
        $device = Device::create([
            'device_code' => 'NODE-TEST-2',
            'firmware_version' => '1.0.0',
        ]);

        // V1 Boundary: negative wind speed, massive cloud cover, invalid IP
        $response = $this->postJson('/api/iot/agrisense/readings', [
            'device_id' => 'NODE-TEST-2',
            'location' => [
                'latitude' => -6.5,
                'longitude' => 107.5,
            ],
            'carbon_data' => [
                'co2_ppm' => 400,
                'ch4_ppm' => -5, // Negative CH4 is invalid
            ],
            'environment' => [
                'cloud_cover_percent' => 150, // Max is 100
                'wind_speed_kmh' => -20, // Min is 0
            ],
            'status' => [
                'ip' => 'invalid-ip-address', // Invalid IP format
            ],
        ]);

        // Expecting 422 if it's protected
        $response->assertStatus(422);
    }
}
