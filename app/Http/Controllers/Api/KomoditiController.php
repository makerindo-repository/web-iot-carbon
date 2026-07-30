<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KomoditiFaseTanam;
use App\Models\KomoditiHamaPenyakit;
use App\Models\KomoditiLingkungan;
use App\Models\KomoditiNutrisi;
use App\Models\KomoditiRekomendasi;
use App\Models\KomoditiSensor;
use App\Models\KomoditiTanaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KomoditiController extends Controller
{
    /**
     * List semua komoditi dengan relasi.
     */
    public function index(Request $request)
    {
        $query = KomoditiTanaman::with(['lingkungan', 'hamaPenyakit', 'sensor', 'faseTanam', 'nutrisi', 'rekomendasi']);

        if ($request->filled('kategori')) {
            $query->where('kategori_tanaman', $request->kategori);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama_komoditi', 'like', "%{$s}%")
                    ->orWhere('kode_komoditi', 'like', "%{$s}%")
                    ->orWhere('nama_latin', 'like', "%{$s}%")
                    ->orWhere('kategori_tanaman', 'like', "%{$s}%");
            });
        }

        return response()->json($query->orderBy('nama_komoditi')->get());
    }

    /**
     * Detail komoditi tunggal.
     */
    public function show($id)
    {
        return response()->json(
            KomoditiTanaman::with(['lingkungan', 'hamaPenyakit', 'sensor', 'faseTanam', 'nutrisi', 'rekomendasi'])->findOrFail($id)
        );
    }

    /**
     * Buat komoditi baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_komoditi' => 'required|string|max:100',
            'kategori_tanaman' => 'required|string|max:100',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Validasi sub-payload (lingkungan, nutrisi, hama_penyakit, dll).
        // Struktur sub-payload mengikuti KomoditiSeeder.php — nama field
        // disinkronkan supaya seeder existing tidak break.
        $decoded = $this->validateSubPayloads($request);

        return DB::transaction(function () use ($request, $decoded) {
            // Auto-generate kode
            $kode = 'KMD-'.strtoupper(Str::random(6));

            $fotoPath = null;
            if ($request->hasFile('foto')) {
                $fotoPath = $request->file('foto')->store('komoditi', 'public');
            }

            $komoditi = KomoditiTanaman::create([
                'kode_komoditi' => $kode,
                'nama_komoditi' => strip_tags($request->nama_komoditi),
                'kategori_tanaman' => strip_tags($request->kategori_tanaman),
                'nama_latin' => $request->nama_latin,
                'varietas' => $request->varietas,
                'deskripsi' => $request->deskripsi,
                'status' => $request->status ?? 'Aktif',
                'foto' => $fotoPath,
                'is_system' => false,
            ]);

            // Sub-tabel lingkungan (pakai hasil decode dari validateSubPayloads)
            if (isset($decoded['lingkungan']) && is_array($decoded['lingkungan'])) {
                KomoditiLingkungan::create(array_merge(
                    ['komoditi_id' => $komoditi->id],
                    $decoded['lingkungan']
                ));
            }

            // Sub-tabel hama/penyakit
            if (isset($decoded['hama_penyakit']) && is_array($decoded['hama_penyakit'])) {
                foreach ($decoded['hama_penyakit'] as $hp) {
                    KomoditiHamaPenyakit::create(array_merge(
                        ['komoditi_id' => $komoditi->id],
                        $hp
                    ));
                }
            }

            // Sub-tabel sensor
            if (isset($decoded['sensor']) && is_array($decoded['sensor'])) {
                KomoditiSensor::create(array_merge(
                    ['komoditi_id' => $komoditi->id],
                    $decoded['sensor']
                ));
            }

            // Sub-tabel fase tanam
            if (isset($decoded['fase_tanam']) && is_array($decoded['fase_tanam'])) {
                KomoditiFaseTanam::create(array_merge(
                    ['komoditi_id' => $komoditi->id],
                    $decoded['fase_tanam']
                ));
            }

            // Sub-tabel nutrisi
            if (isset($decoded['nutrisi']) && is_array($decoded['nutrisi'])) {
                KomoditiNutrisi::create(array_merge(
                    ['komoditi_id' => $komoditi->id],
                    $decoded['nutrisi']
                ));
            }

            // Sub-tabel rekomendasi
            if (isset($decoded['rekomendasi']) && is_array($decoded['rekomendasi'])) {
                KomoditiRekomendasi::create(array_merge(
                    ['komoditi_id' => $komoditi->id],
                    $decoded['rekomendasi']
                ));
            }

            return response()->json(
                KomoditiTanaman::with(['lingkungan', 'hamaPenyakit', 'sensor', 'faseTanam', 'nutrisi', 'rekomendasi'])->find($komoditi->id),
                201
            );
        });
    }

    /**
     * Update komoditi.
     */
    public function update(Request $request, $id)
    {
        $komoditi = KomoditiTanaman::findOrFail($id);

        // Validasi sub-payload (sama seperti store).
        $decoded = $this->validateSubPayloads($request);

        return DB::transaction(function () use ($request, $komoditi, $decoded) {
            // Proteksi: data system tidak boleh diubah field kritis
            $updateData = $request->only([
                'nama_latin', 'varietas', 'deskripsi', 'status',
            ]);

            if ($request->has('nama_komoditi')) {
                $updateData['nama_komoditi'] = strip_tags($request->nama_komoditi);
            }
            if ($request->has('kategori_tanaman')) {
                $updateData['kategori_tanaman'] = strip_tags($request->kategori_tanaman);
            }

            // Non-system boleh update semua
            if (! $komoditi->is_system) {
                $updateData = array_merge($updateData, $request->only(['fapar', 'epsilon_max']));
            }

            if ($request->hasFile('foto')) {
                if ($komoditi->foto) {
                    Storage::disk('public')->delete($komoditi->foto);
                }
                $updateData['foto'] = $request->file('foto')->store('komoditi', 'public');
            }

            $komoditi->update($updateData);

            // Update lingkungan (pakai hasil decode dari validateSubPayloads)
            if (isset($decoded['lingkungan']) && is_array($decoded['lingkungan'])) {
                $komoditi->lingkungan()->updateOrCreate(
                    ['komoditi_id' => $komoditi->id],
                    $decoded['lingkungan']
                );
            }

            // Update hama/penyakit (replace all)
            if (isset($decoded['hama_penyakit']) && is_array($decoded['hama_penyakit'])) {
                $komoditi->hamaPenyakit()->delete();
                foreach ($decoded['hama_penyakit'] as $hp) {
                    KomoditiHamaPenyakit::create(array_merge(
                        ['komoditi_id' => $komoditi->id],
                        $hp
                    ));
                }
            }

            // Update sensor
            if (isset($decoded['sensor']) && is_array($decoded['sensor'])) {
                $komoditi->sensor()->updateOrCreate(
                    ['komoditi_id' => $komoditi->id],
                    $decoded['sensor']
                );
            }

            // Update fase tanam
            if (isset($decoded['fase_tanam']) && is_array($decoded['fase_tanam'])) {
                $komoditi->faseTanam()->updateOrCreate(
                    ['komoditi_id' => $komoditi->id],
                    $decoded['fase_tanam']
                );
            }

            // Update nutrisi
            if (isset($decoded['nutrisi']) && is_array($decoded['nutrisi'])) {
                $komoditi->nutrisi()->updateOrCreate(
                    ['komoditi_id' => $komoditi->id],
                    $decoded['nutrisi']
                );
            }

            // Update rekomendasi
            if (isset($decoded['rekomendasi']) && is_array($decoded['rekomendasi'])) {
                $komoditi->rekomendasi()->updateOrCreate(
                    ['komoditi_id' => $komoditi->id],
                    $decoded['rekomendasi']
                );
            }

            return response()->json(
                KomoditiTanaman::with(['lingkungan', 'hamaPenyakit', 'sensor', 'faseTanam', 'nutrisi', 'rekomendasi'])->find($komoditi->id)
            );
        });
    }

    /**
     * Hapus komoditi.
     */
    public function destroy($id)
    {
        $komoditi = KomoditiTanaman::findOrFail($id);

        if ($komoditi->is_system) {
            return response()->json([
                'message' => 'Data komoditi bawaan sistem tidak dapat dihapus.',
            ], 403);
        }

        $komoditi->delete(); // Cascade akan menghapus sub-tabel

        return response()->json(['message' => 'Komoditi berhasil dihapus.']);
    }

    /**
     * Daftar kategori unik.
     */
    public function categories()
    {
        return response()->json(
            KomoditiTanaman::select('kategori_tanaman')
                ->distinct()
                ->orderBy('kategori_tanaman')
                ->pluck('kategori_tanaman')
        );
    }

    /**
     * Validasi sub-payload (lingkungan, hama_penyakit, sensor, fase_tanam,
     * nutrisi, rekomendasi). Field name disinkronkan dengan KomoditiSeeder.php
     * supaya seeder existing tidak break.
     *
     * Sub-payload bisa datang sebagai JSON string (dari multipart form-data)
     * atau associative array — keduanya di-decode dulu sebelum di-validate.
     *
     * Throws ValidationException kalau ada field yang gagal validasi.
     *
     * Mengembalikan map sub-payload yang sudah di-decode & tervalidasi (hanya key
     * yang ada di request), supaya store()/update() TIDAK perlu men-decode ulang
     * field mentah dari request (menghindari kerja ganda & risiko divergensi).
     */
    private function validateSubPayloads(Request $request): array
    {
        // Decode sub-payload ke array (handle JSON string dari multipart)
        $decoded = [];
        foreach (['lingkungan', 'hama_penyakit', 'sensor', 'fase_tanam', 'nutrisi', 'rekomendasi'] as $key) {
            if (! $request->has($key)) {
                continue;
            }
            $value = $request->input($key);

            if (is_string($value)) {
                $parsed = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw ValidationException::withMessages([
                        $key => ['Format data '.$key.' tidak valid (harus berupa JSON atau Array).'],
                    ]);
                }
                $decoded[$key] = $parsed;
            } else {
                $decoded[$key] = $value;
            }
        }

        if (empty($decoded)) {
            return $decoded;
        }

        Validator::make($decoded, [
            // Setiap sub-payload tunggal WAJIB objek/array. Cegah skalar (mis. 123
            // atau "123") yang lolos validasi lalu memicu TypeError pada array_merge → 500.
            'lingkungan' => 'nullable|array',
            'sensor' => 'nullable|array',
            'fase_tanam' => 'nullable|array',
            'nutrisi' => 'nullable|array',
            // Tiap elemen hama_penyakit di-iterasi + array_merge → wajib objek juga.
            'hama_penyakit.*' => 'array',

            // Lingkungan — nama field IKUT seeder (kelembapan_udara_min, bukan kelembapan_min)
            'lingkungan.suhu_min' => 'nullable|numeric',
            'lingkungan.suhu_max' => 'nullable|numeric',
            'lingkungan.kelembapan_udara_min' => 'nullable|numeric|between:0,100',
            'lingkungan.kelembapan_udara_max' => 'nullable|numeric|between:0,100',
            'lingkungan.ph_min' => 'nullable|numeric|between:0,14',
            'lingkungan.ph_max' => 'nullable|numeric|between:0,14',
            'lingkungan.curah_hujan_min' => 'nullable|numeric|min:0',
            'lingkungan.curah_hujan_max' => 'nullable|numeric|min:0',
            'lingkungan.ketinggian_min' => 'nullable|numeric',
            'lingkungan.ketinggian_max' => 'nullable|numeric',
            'lingkungan.intensitas_cahaya' => 'nullable|string|max:50',
            'lingkungan.jenis_tanah' => 'nullable|string|max:100',
            'lingkungan.drainase' => 'nullable|string|max:50',

            // Nutrisi — pakai range min/max sesuai seeder (bukan _ppm single)
            'nutrisi.nitrogen_min' => 'nullable|numeric|min:0',
            'nutrisi.nitrogen_max' => 'nullable|numeric|min:0',
            'nutrisi.fosfor_min' => 'nullable|numeric|min:0',
            'nutrisi.fosfor_max' => 'nullable|numeric|min:0',
            'nutrisi.kalium_min' => 'nullable|numeric|min:0',
            'nutrisi.kalium_max' => 'nullable|numeric|min:0',
            'nutrisi.satuan_npk' => 'nullable|string|max:20',
            'nutrisi.bahan_organik_min' => 'nullable|numeric|min:0',
            'nutrisi.bahan_organik_max' => 'nullable|numeric|min:0',
            'nutrisi.rekomendasi_pemupukan' => 'nullable|string|max:1000',

            // Hama/Penyakit — case-insensitive enum (seeder pakai 'Tinggi'/'Sedang' kapital)
            'hama_penyakit' => 'nullable|array',
            'hama_penyakit.*.nama' => 'nullable|string|max:100',
            'hama_penyakit.*.jenis' => 'nullable|string|in:Hama,Penyakit,hama,penyakit',
            'hama_penyakit.*.tingkat_risiko' => 'nullable|string|in:Rendah,Sedang,Tinggi,rendah,sedang,tinggi',
            'hama_penyakit.*.gejala' => 'nullable|string|max:500',
            'hama_penyakit.*.pengendalian' => 'nullable|string|max:1000',

            // Fase tanam
            'fase_tanam.usia_tanam_min' => 'nullable|integer|min:1|max:1000',
            'fase_tanam.usia_tanam_max' => 'nullable|integer|min:1|max:1000',
            'fase_tanam.satuan_usia' => 'nullable|string|in:hari,minggu,bulan',
            'fase_tanam.fase_pembibitan' => 'nullable|string|max:100',
            'fase_tanam.fase_vegetatif' => 'nullable|string|max:100',
            'fase_tanam.fase_generatif' => 'nullable|string|max:100',
            'fase_tanam.fase_panen' => 'nullable|string|max:100',
            'fase_tanam.catatan_budidaya' => 'nullable|string|max:2000',

            // Sensor — boolean toggles + parameter_kritis fleksibel
            'sensor.sensor_suhu' => 'nullable|boolean',
            'sensor.sensor_kelembapan_udara' => 'nullable|boolean',
            'sensor.sensor_kelembapan_tanah' => 'nullable|boolean',
            'sensor.sensor_ph_tanah' => 'nullable|boolean',
            'sensor.sensor_npk' => 'nullable|boolean',
            'sensor.sensor_co2' => 'nullable|boolean',
            'sensor.sensor_curah_hujan' => 'nullable|boolean',
            'sensor.sensor_cahaya' => 'nullable|boolean',
            // parameter_kritis bisa array atau JSON string (seeder pakai json_encode)
            'sensor.parameter_kritis' => 'nullable',

            // Rekomendasi — fleksibel (struktur bisa beragam)
            'rekomendasi' => 'nullable|array',
        ])->validate();

        return $decoded;
    }
}
