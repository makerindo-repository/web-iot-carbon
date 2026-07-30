<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Utama Komoditi
        Schema::create('komoditi_tanaman', function (Blueprint $table) {
            $table->id();
            $table->string('kode_komoditi', 20)->unique();
            $table->string('nama_komoditi', 100);
            $table->string('kategori_tanaman', 100);
            $table->string('nama_latin', 150)->nullable();
            $table->string('varietas', 100)->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('status', 20)->default('Aktif'); // Aktif, Draft, Nonaktif
            $table->decimal('fapar', 5, 3)->nullable();       // fAPAR ilmiah
            $table->decimal('epsilon_max', 5, 3)->nullable();  // εmax ilmiah
            $table->boolean('is_system')->default(false);      // Proteksi data bawaan
            $table->timestamps();
        });

        // 2. Tabel Karakteristik Lingkungan Ideal
        Schema::create('komoditi_lingkungan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komoditi_id')->constrained('komoditi_tanaman')->onDelete('cascade');
            $table->decimal('suhu_min', 5, 2)->nullable();
            $table->decimal('suhu_max', 5, 2)->nullable();
            $table->decimal('kelembapan_udara_min', 5, 2)->nullable();
            $table->decimal('kelembapan_udara_max', 5, 2)->nullable();
            $table->decimal('kelembapan_tanah_min', 5, 2)->nullable();
            $table->decimal('kelembapan_tanah_max', 5, 2)->nullable();
            $table->decimal('ph_min', 4, 2)->nullable();
            $table->decimal('ph_max', 4, 2)->nullable();
            $table->string('intensitas_cahaya', 50)->nullable(); // Penuh, Sedang, Teduh
            $table->integer('curah_hujan_min')->nullable();
            $table->integer('curah_hujan_max')->nullable();
            $table->integer('ketinggian_min')->nullable();
            $table->integer('ketinggian_max')->nullable();
            $table->string('jenis_tanah', 150)->nullable();
            $table->string('drainase', 100)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        // 3. Tabel Hama dan Penyakit
        Schema::create('komoditi_hama_penyakit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komoditi_id')->constrained('komoditi_tanaman')->onDelete('cascade');
            $table->string('nama', 150);
            $table->string('jenis', 20)->default('Hama'); // Hama, Penyakit
            $table->text('gejala')->nullable();
            $table->string('tingkat_risiko', 20)->nullable(); // Rendah, Sedang, Tinggi
            $table->text('pengendalian')->nullable();
            $table->timestamps();
        });

        // 4. Tabel Sensor Terkait
        Schema::create('komoditi_sensor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komoditi_id')->constrained('komoditi_tanaman')->onDelete('cascade');
            $table->boolean('sensor_suhu')->default(true);
            $table->boolean('sensor_kelembapan_udara')->default(true);
            $table->boolean('sensor_kelembapan_tanah')->default(true);
            $table->boolean('sensor_ph_tanah')->default(true);
            $table->boolean('sensor_npk')->default(false);
            $table->boolean('sensor_cahaya')->default(false);
            $table->boolean('sensor_co2')->default(false);
            $table->boolean('sensor_curah_hujan')->default(false);
            $table->json('parameter_kritis')->nullable();
            $table->timestamps();
        });

        // 5. Tambah kolom komoditi_id ke tabel gardens
        Schema::table('gardens', function (Blueprint $table) {
            $table->foreignId('komoditi_id')->nullable()->after('plant_id')->constrained('komoditi_tanaman')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('gardens', function (Blueprint $table) {
            $table->dropForeign(['komoditi_id']);
            $table->dropColumn('komoditi_id');
        });
        Schema::dropIfExists('komoditi_sensor');
        Schema::dropIfExists('komoditi_hama_penyakit');
        Schema::dropIfExists('komoditi_lingkungan');
        Schema::dropIfExists('komoditi_tanaman');
    }
};
