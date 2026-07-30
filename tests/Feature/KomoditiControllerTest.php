<?php

namespace Tests\Feature;

use App\Models\KomoditiLingkungan;
use App\Models\KomoditiTanaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KomoditiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private User $viewer;

    private KomoditiTanaman $systemKomoditi;

    private KomoditiTanaman $customKomoditi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->operator = User::factory()->create(['role' => 'operator']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);

        $this->systemKomoditi = KomoditiTanaman::create([
            'kode_komoditi' => 'KMD-SYS01',
            'nama_komoditi' => 'Padi Sawah',
            'kategori_tanaman' => 'Pangan',
            'status' => 'Aktif',
            'is_system' => true,
        ]);

        $this->customKomoditi = KomoditiTanaman::create([
            'kode_komoditi' => 'KMD-CUST01',
            'nama_komoditi' => 'Tomat Cherry',
            'kategori_tanaman' => 'Sayuran',
            'status' => 'Aktif',
            'is_system' => false,
        ]);
    }

    // ── Happy Path Tests ──

    /** @test */
    public function can_list_all_komoditi(): void
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/komoditi');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    /** @test */
    public function can_filter_komoditi_by_kategori(): void
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/komoditi?kategori=Sayuran');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.nama_komoditi', 'Tomat Cherry');
    }

    /** @test */
    public function can_search_komoditi(): void
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/komoditi?search=Padi');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.nama_komoditi', 'Padi Sawah');
    }

    /** @test */
    public function can_show_single_komoditi_with_relations(): void
    {
        KomoditiLingkungan::create([
            'komoditi_id' => $this->systemKomoditi->id,
            'suhu_min' => 20,
            'suhu_max' => 30,
        ]);

        $response = $this->actingAs($this->viewer)
            ->getJson("/api/komoditi/{$this->systemKomoditi->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('nama_komoditi', 'Padi Sawah');
        $response->assertJsonPath('lingkungan.suhu_min', 20);
    }

    /** @test */
    public function admin_can_create_komoditi_with_sub_payloads(): void
    {
        $payload = [
            'nama_komoditi' => 'Cabai Rawit',
            'kategori_tanaman' => 'Sayuran',
            'lingkungan' => [
                'suhu_min' => 25,
                'suhu_max' => 32,
            ],
            'fase_tanam' => [
                'usia_tanam_max' => 120,
                'satuan_usia' => 'hari',
            ],
            'hama_penyakit' => [
                [
                    'nama' => 'Kutu Daun',
                    'jenis' => 'Hama',
                    'tingkat_risiko' => 'Tinggi',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/komoditi', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('nama_komoditi', 'Cabai Rawit');
        $this->assertNotNull($response->json('kode_komoditi'));
        $this->assertFalse($response->json('is_system'));

        // Assert sub-relations created
        $this->assertDatabaseHas('komoditi_lingkungan', ['suhu_min' => 25]);
        $this->assertDatabaseHas('komoditi_fase_tanam', ['usia_tanam_max' => 120]);
        $this->assertDatabaseHas('komoditi_hama_penyakit', ['nama' => 'Kutu Daun']);
    }

    /** @test */
    public function admin_can_create_komoditi_with_json_string_payloads(): void
    {
        // Testing multipart form-data simulation where sub-payloads are JSON strings
        $payload = [
            'nama_komoditi' => 'Bawang Merah',
            'kategori_tanaman' => 'Sayuran',
            'lingkungan' => json_encode(['suhu_min' => 22]),
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/komoditi', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('komoditi_lingkungan', ['suhu_min' => 22]);
    }

    /** @test */
    public function admin_can_update_custom_komoditi(): void
    {
        $payload = [
            'nama_komoditi' => 'Tomat Cherry Updated',
            'lingkungan' => [
                'suhu_min' => 18,
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->putJson("/api/komoditi/{$this->customKomoditi->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('nama_komoditi', 'Tomat Cherry Updated');
        $this->assertDatabaseHas('komoditi_lingkungan', [
            'komoditi_id' => $this->customKomoditi->id,
            'suhu_min' => 18,
        ]);
    }

    /** @test */
    public function admin_can_delete_custom_komoditi(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/komoditi/{$this->customKomoditi->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('komoditi_tanaman', ['id' => $this->customKomoditi->id]);
    }

    // ── Protection & Validation Tests ──

    /** @test */
    public function cannot_delete_system_komoditi(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/komoditi/{$this->systemKomoditi->id}");

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Data komoditi bawaan sistem tidak dapat dihapus.');
        $this->assertDatabaseHas('komoditi_tanaman', ['id' => $this->systemKomoditi->id]);
    }

    /** @test */
    public function viewer_cannot_create_komoditi(): void
    {
        $response = $this->actingAs($this->viewer)
            ->postJson('/api/komoditi', [
                'nama_komoditi' => 'Test',
                'kategori_tanaman' => 'Test',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function create_sanitizes_xss_in_nama_komoditi(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/komoditi', [
                'nama_komoditi' => '<script>alert(1)</script>Jagung',
                'kategori_tanaman' => 'Pangan',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('alert(1)Jagung', $response->json('nama_komoditi'));
    }

    /** @test */
    public function validation_fails_if_sub_payload_is_invalid_json(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/komoditi', [
                'nama_komoditi' => 'Test',
                'kategori_tanaman' => 'Test',
                'lingkungan' => 'invalid-json-string',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['lingkungan']);
    }

    /** @test */
    public function admin_can_upload_foto_komoditi(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('komoditi.jpg');

        $response = $this->actingAs($this->admin)
            ->postJson('/api/komoditi', [
                'nama_komoditi' => 'Foto Test',
                'kategori_tanaman' => 'Buah',
                'foto' => $file,
            ]);

        $response->assertStatus(201);
        $fotoPath = $response->json('foto');
        $this->assertNotNull($fotoPath);
        Storage::disk('public')->assertExists($fotoPath);
    }

    /** @test */
    public function can_get_unique_categories(): void
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/komoditi/categories');

        $response->assertStatus(200);
        $categories = $response->json();
        $this->assertContains('Pangan', $categories);
        $this->assertContains('Sayuran', $categories);
    }
}
