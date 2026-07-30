<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('komoditi_rekomendasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komoditi_id')->constrained('komoditi_tanaman')->onDelete('cascade');
            $table->string('status_kesesuaian_lahan', 50)->nullable(); // Sesuai, Tidak Sesuai, dll
            $table->integer('skor_kesesuaian')->nullable(); // 0-100
            $table->string('kategori_rekomendasi', 100)->nullable();
            $table->json('parameter_bermasalah')->nullable();
            $table->text('rekomendasi_tindakan')->nullable();
            $table->string('prioritas_tindakan', 50)->nullable(); // Rendah, Sedang, Tinggi
            $table->text('catatan_rekomendasi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('komoditi_rekomendasis');
    }
};
