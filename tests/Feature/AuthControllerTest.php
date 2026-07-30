<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@agrisense.id',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
    }

    // ── Login Tests ──

    /** @test */
    public function user_can_login_with_correct_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@agrisense.id',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'token',
            'user' => [
                'id',
                'name',
                'email',
                'role',
            ],
        ]);
        $this->assertEquals('admin', $response->json('user.role'));
    }

    /** @test */
    public function user_cannot_login_with_incorrect_password(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@agrisense.id',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@agrisense.id',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    // ── Profile Tests ──

    /** @test */
    public function user_can_update_profile_name(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/profile', [
                'name' => 'Updated Name',
                'email' => 'test@agrisense.id',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('user.name', 'Updated Name');
        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'name' => 'Updated Name']);
    }

    /** @test */
    public function user_can_change_password_with_correct_current_password(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/profile/password', [
                'current_password' => 'password123',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        // Verify can login with new password
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'test@agrisense.id',
            'password' => 'newpassword123',
        ]);
        $loginResponse->assertStatus(200);
    }

    /** @test */
    public function user_cannot_change_password_with_incorrect_current_password(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/profile/password', [
                'current_password' => 'wrongcurrent',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Password lama tidak cocok.');
    }

    /** @test */
    public function password_change_requires_confirmation(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/profile/password', [
                'current_password' => 'password123',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'mismatch',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['new_password']);
    }

    // ── Logout Tests ──

    /** @test */
    public function user_can_logout(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/logout');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Logged out successfully');

        // Verify token is deleted
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
