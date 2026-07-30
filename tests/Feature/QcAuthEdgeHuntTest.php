<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QC Edge Hunt Test Suite — 8 Vektor Attack
 * Target: Otentikasi & Manajemen Pengguna (Security & Users)
 */
class QcAuthEdgeHuntTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🔐 AUTHENTICATION (Login & Logout)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** V1: Boundary — Password extremely long (BDoS attack via bcrypt) */
    public function test_login_extremely_long_password(): void
    {
        $longPassword = str_repeat('a', 10000); // 10,000 chars

        // Measure time taken. If bcrypt tries to hash 10,000 chars, it could take a long time and cause DoS.
        // Laravel's default max length for password is usually 64 or 255. Let's see if it's protected.
        $startTime = microtime(true);
        $response = $this->postJson('/api/login', [
            'email' => $this->admin->email,
            'password' => $longPassword,
        ]);
        $duration = microtime(true) - $startTime;

        // Validasi Laravel di controller max:64 harusnya memblokir dalam < 0.5 detik (422)
        $response->assertStatus(422);
        $this->assertLessThan(1.0, $duration, 'Password hash hashing took too long! Potential BDoS vulnerability.');
    }

    /** V3: Type Confusion — password is an array instead of string */
    public function test_login_password_type_confusion(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->admin->email,
            'password' => ['password123'], // Mengirim array
        ]);
        $response->assertStatus(422);
    }

    /** V2: Null Strike — Empty payload */
    public function test_login_empty_payload(): void
    {
        $response = $this->postJson('/api/login', []);
        $response->assertStatus(422);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 👥 USER MANAGEMENT (Admin Only)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** V5: Role Break — Viewer trying to create a user (Elevation of Privilege) */
    public function test_viewer_cannot_create_user(): void
    {
        $this->actingAs($this->viewer);

        $response = $this->postJson('/api/users', [
            'name' => 'Hacked Admin',
            'email' => 'hacked@admin.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);
        // Viewer harus ditolak middleware role:admin
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    /** V5: Role Break — Viewer trying to promote themselves to admin via updateUser */
    public function test_viewer_cannot_promote_themselves(): void
    {
        $this->actingAs($this->viewer);

        $response = $this->putJson('/api/users/'.$this->viewer->id, [
            'role' => 'admin',
        ]);
        // Viewer harus ditolak middleware role:admin
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    /** V5: Role Break — Admin deleting themselves */
    public function test_admin_cannot_delete_themselves(): void
    {
        $this->actingAs($this->admin);

        $response = $this->deleteJson('/api/users/'.$this->admin->id);
        // Harus dicegah di controller
        $response->assertStatus(403);
        $response->assertJsonPath('status', 'error');
    }

    /** V2: Null Strike — Update user with empty role string */
    public function test_admin_update_user_empty_role(): void
    {
        $this->actingAs($this->admin);

        $response = $this->putJson('/api/users/'.$this->viewer->id, [
            'role' => '',
        ]);
        // Harus ditolak karena in:admin,operator,viewer
        $response->assertStatus(422);
    }

    /** V1: Boundary — Email excessively long causing SQL 500 error */
    public function test_admin_create_user_long_email(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/users', [
            'name' => 'Test',
            'email' => str_repeat('a', 300).'@test.com',
            'password' => 'password123',
            'role' => 'viewer',
        ]);

        $this->assertNotEquals(500, $response->getStatusCode(), 'Crash 500 on too long email');
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 👤 PROFILE & PASSWORD UPDATE
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** V7: Encoding Ambush — XSS in profile name */
    public function test_profile_update_xss_name(): void
    {
        $this->actingAs($this->viewer);

        $response = $this->putJson('/api/profile', [
            'name' => '<script>alert(document.cookie)</script>',
        ]);
        // Seharusnya tidak error 500, tetapi 200 OK jika disimpan as-is, atau 422 jika dilarang
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    /** V5: Role Break — Try to change another user's password via updateProfile?
     *  Wait, updateProfile does not take user ID, it uses $request->user().
     *  Let's test if someone can change password without providing the old password.
     */
    public function test_change_password_without_current_password(): void
    {
        $this->actingAs($this->viewer);

        $response = $this->putJson('/api/profile/password', [
            'new_password' => 'HackedPassword123!',
            'new_password_confirmation' => 'HackedPassword123!',
        ]);
        // Harus wajib ada current_password
        $response->assertStatus(422);
    }

    /** V4: State Collision — Wrong current password */
    public function test_change_password_wrong_current(): void
    {
        $this->actingAs($this->viewer);

        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'WrongPassword123!',
            'new_password' => 'HackedPassword123!',
            'new_password_confirmation' => 'HackedPassword123!',
        ]);
        // current_password salah
        $response->assertStatus(422);
    }

    /** V7: Large Base64 image payload (10MB) for profile picture (DoS via memory exhaustion) */
    public function test_profile_photo_dos_large_base64(): void
    {
        $this->actingAs($this->admin);

        // Buat string base64 yang sangat besar (~3MB payload = > 1MB limit)
        $giantBase64 = 'data:image/png;base64,'.str_repeat('A', 3 * 1024 * 1024);

        $startTime = microtime(true);
        $response = $this->putJson('/api/profile', [
            'name' => 'Admin Besar',
            'profile_photo' => $giantBase64,
        ]);
        $duration = microtime(true) - $startTime;

        // Seharusnya diabaikan secara graceful tanpa menyebabkan crash (500)
        // Controller: return null (abaikan foto) dan kembalikan 200/OK
        $this->assertNotEquals(500, $response->getStatusCode());
        // Memastikan proses reject berjalan cepat dan tidak memory leak
        $this->assertLessThan(2.0, $duration, 'Profile photo validation took too long, potential memory exhaustion DoS.');
    }
}
