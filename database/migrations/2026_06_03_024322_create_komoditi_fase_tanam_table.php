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
        Schema::create('komoditi_fase_tanam', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komoditi_id')->constrained('komoditi_tanaman')->onDelete('cascade');
            $table->integer('usia_tanam_min')->nullable();
            $table->integer('usia_tanam_max')->nullable();
            $table->string('satuan_usia', 20)->default('hari'); // hari, bulan, tahun
            $table->string('fase_pembibitan', 100)->nullable();
            $table->string('fase_vegetatif', 100)->nullable();
            $table->string('fase_generatif', 100)->nullable();
            $table->string('fase_panen', 100)->nullable();
            $table->text('catatan_budidaya')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('komoditi_fase_tanam');
    }
};
