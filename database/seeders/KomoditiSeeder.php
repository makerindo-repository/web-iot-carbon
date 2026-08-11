<?php

namespace Database\Seeders;

use App\Models\KomoditiFaseTanam;
use App\Models\KomoditiHamaPenyakit;
use App\Models\KomoditiLingkungan;
use App\Models\KomoditiNutrisi;
use App\Models\KomoditiRekomendasi;
use App\Models\KomoditiSensor;
use App\Models\KomoditiTanaman;
use Illuminate\Database\Seeder;

class KomoditiSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'kode' => 'KMD-007', 'nama' => 'Cabai', 'kategori' => 'Hortikultura (Sayur dan Bumbu)',
                'latin' => 'Capsicum annuum', 'varietas' => 'Rawit, Keriting, Merah Besar',
                'deskripsi' => 'Komoditas hortikultura strategis dengan nilai ekonomi tinggi.',
                'fapar' => 0.650, 'epsilon' => 1.400,
                'lingkungan' => ['suhu_min' => 24, 'suhu_max' => 28, 'kelembapan_udara_min' => 60, 'kelembapan_udara_max' => 80, 'ph_min' => 6.0, 'ph_max' => 6.8, 'intensitas_cahaya' => 'Penuh', 'curah_hujan_min' => 600, 'curah_hujan_max' => 1200, 'ketinggian_min' => 0, 'ketinggian_max' => 1200, 'jenis_tanah' => 'Lempung gembur', 'drainase' => 'Baik'],
                'hama' => [
                    ['nama' => 'Thrips', 'jenis' => 'Hama', 'tingkat_risiko' => 'Tinggi', 'gejala' => 'Daun keriting, bercak keperakan', 'pengendalian' => 'Mulsa perak, insektisida'],
                    ['nama' => 'Antraknosa', 'jenis' => 'Penyakit', 'tingkat_risiko' => 'Tinggi', 'gejala' => 'Bercak hitam pada buah', 'pengendalian' => 'Fungisida, drainase baik'],
                ],
                'fase_tanam' => ['usia_tanam_min' => 80, 'usia_tanam_max' => 100, 'satuan_usia' => 'hari', 'fase_pembibitan' => '0-25 hari', 'fase_vegetatif' => '26-50 hari', 'fase_generatif' => '51-80 hari', 'fase_panen' => '80-100 hari', 'catatan_budidaya' => 'Panen dilakukan secara bertahap setiap 3-5 hari.'],
                'nutrisi' => ['nitrogen_min' => 0.3, 'nitrogen_max' => 0.6, 'fosfor_min' => 15, 'fosfor_max' => 30, 'kalium_min' => 100, 'kalium_max' => 200, 'satuan_npk' => 'ppm', 'bahan_organik_min' => 2.5, 'bahan_organik_max' => 5.0, 'rekomendasi_pemupukan' => 'Gunakan pupuk NPK tinggi Kalium saat fase generatif.'],
            ],
            [
                'kode' => 'KMD-011', 'nama' => 'Sawi', 'kategori' => 'Hortikultura (Sayur dan Bumbu)',
                'latin' => 'Brassica juncea', 'varietas' => 'Caisim, Pakcoy',
                'deskripsi' => 'Sayuran daun populer, tumbuh cepat di dataran rendah-menengah.',
                'fapar' => 0.620, 'epsilon' => 1.300,
                'lingkungan' => ['suhu_min' => 18, 'suhu_max' => 27, 'kelembapan_udara_min' => 60, 'kelembapan_udara_max' => 80, 'ph_min' => 6.0, 'ph_max' => 7.0, 'intensitas_cahaya' => 'Penuh', 'ketinggian_min' => 0, 'ketinggian_max' => 1000, 'jenis_tanah' => 'Lempung gembur', 'drainase' => 'Baik'],
                'hama' => [['nama' => 'Ulat tritip', 'jenis' => 'Hama', 'tingkat_risiko' => 'Sedang', 'gejala' => 'Daun berlubang kecil', 'pengendalian' => 'Perangkap warna kuning']],
                'fase_tanam' => ['usia_tanam_min' => 30, 'usia_tanam_max' => 45, 'satuan_usia' => 'hari', 'fase_pembibitan' => '0-10 hari', 'fase_vegetatif' => '11-30 hari', 'fase_generatif' => 'Tidak relevan', 'fase_panen' => '30-45 hari', 'catatan_budidaya' => 'Panen dilakukan sebelum tanaman berbunga.'],
                'nutrisi' => ['nitrogen_min' => 0.4, 'nitrogen_max' => 0.8, 'fosfor_min' => 10, 'fosfor_max' => 20, 'kalium_min' => 60, 'kalium_max' => 120, 'satuan_npk' => 'ppm', 'bahan_organik_min' => 3.0, 'bahan_organik_max' => 6.0, 'rekomendasi_pemupukan' => 'Gunakan pupuk NPK tinggi Nitrogen untuk pertumbuhan daun.'],
            ],
        ];

        // Kosongkan tabel sebelum melakukan seeding agar tidak bertumpuk jika dijalankan ulang
        KomoditiTanaman::query()->delete();

        foreach ($data as $item) {
            $komoditi = KomoditiTanaman::updateOrCreate(
                ['kode_komoditi' => $item['kode']],
                [
                    'nama_komoditi' => $item['nama'],
                    'kategori_tanaman' => $item['kategori'],
                    'nama_latin' => $item['latin'],
                    'varietas' => $item['varietas'],
                    'deskripsi' => $item['deskripsi'],
                    'status' => 'Aktif',
                    'fapar' => $item['fapar'],
                    'epsilon_max' => $item['epsilon'],
                    'is_system' => true,
                ]
            );

            // Lingkungan
            if (isset($item['lingkungan'])) {
                KomoditiLingkungan::updateOrCreate(
                    ['komoditi_id' => $komoditi->id],
                    $item['lingkungan']
                );
            }

            // Hama/Penyakit
            if (isset($item['hama'])) {
                KomoditiHamaPenyakit::where('komoditi_id', $komoditi->id)->delete();
                foreach ($item['hama'] as $hp) {
                    KomoditiHamaPenyakit::create(array_merge(
                        ['komoditi_id' => $komoditi->id],
                        $hp
                    ));
                }
            }

            // Fase Tanam
            if (isset($item['fase_tanam'])) {
                KomoditiFaseTanam::updateOrCreate(
                    ['komoditi_id' => $komoditi->id],
                    $item['fase_tanam']
                );
            }

            // Nutrisi
            if (isset($item['nutrisi'])) {
                KomoditiNutrisi::updateOrCreate(
                    ['komoditi_id' => $komoditi->id],
                    $item['nutrisi']
                );
            }

            // Rekomendasi
            if (isset($item['rekomendasi'])) {
                KomoditiRekomendasi::updateOrCreate(
                    ['komoditi_id' => $komoditi->id],
                    $item['rekomendasi']
                );
            }

            // Sensor (default: semua true kecuali curah hujan)
            KomoditiSensor::updateOrCreate(
                ['komoditi_id' => $komoditi->id],
                [
                    'sensor_suhu' => true,
                    'sensor_kelembapan_udara' => true,
                    'sensor_kelembapan_tanah' => true,
                    'sensor_ph_tanah' => true,
                    'sensor_npk' => true,
                    'sensor_cahaya' => true,
                    'sensor_co2' => true,
                    'sensor_curah_hujan' => false,
                    'parameter_kritis' => json_encode(['suhu', 'kelembapan_tanah', 'ph_tanah']),
                ]
            );
        }
    }
}
