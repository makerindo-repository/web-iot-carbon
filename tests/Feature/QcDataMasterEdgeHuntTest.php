<?php

namespace Tests\Feature;

use App\Models\KomoditiTanaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * QC Edge Hunt Test Suite — 8 Vektor Attack
 * Target: Master Data (Komoditi Tanaman, dsb)
 */
class QcDataMasterEdgeHuntTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🌾 KOMODITI TANAMAN
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** V2: Null Strike — Missing required fields */
    public function test_komoditi_missing_required(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/komoditi', []);
        $response->assertStatus(422);
    }

    /** V3: Type Confusion — Sending sub-payload as wrong type (array vs JSON string) */
    public function test_komoditi_type_confusion_subpayload(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/komoditi', [
            'nama_komoditi' => 'Tomat',
            'kategori_tanaman' => 'Sayuran Buah',
            'lingkungan' => 'bukan-json', // Harusnya valid JSON string atau array
        ]);

        // json_decode akan gagal dan return null/false, kemudian validator akan gagal (500 atau 422)
        // Kita harapkan server TIDAK crash (500)
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    /** V1: Boundary Attack — Upload foto terlalu besar */
    public function test_komoditi_large_photo(): void
    {
        $this->actingAs($this->admin);
        Storage::fake('public');

        // Buat fake file 5MB (Batas 2MB)
        $file = UploadedFile::fake()->image('giant.jpg')->size(5000);

        $response = $this->postJson('/api/komoditi', [
            'nama_komoditi' => 'Cabai Besar',
            'kategori_tanaman' => 'Sayuran',
            'foto' => $file,
        ]);

        $response->assertStatus(422);
    }

    /** V5: Role Break — System data cannot be deleted */
    public function test_komoditi_system_delete(): void
    {
        $this->actingAs($this->admin);

        $komoditi = KomoditiTanaman::create([
            'kode_komoditi' => 'KMD-SYS',
            'nama_komoditi' => 'Padi Sawah',
            'kategori_tanaman' => 'Pangan',
            'is_system' => true, // Data bawaan sistem
        ]);

        $response = $this->deleteJson('/api/komoditi/'.$komoditi->id);

        // Harus dicegah di controller dengan 403
        $response->assertStatus(403);
    }

    /** V7: Encoding Ambush — XSS in Komoditi name */
    public function test_komoditi_xss_name(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/komoditi', [
            'nama_komoditi' => '<script>alert("Hacked")</script>',
            'kategori_tanaman' => 'XSS',
        ]);

        $this->assertNotEquals(500, $response->getStatusCode());
    }
}
