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
        Schema::create('plantings', function (Blueprint $table) {
            $table->id();
            $table->string('nama_tanaman'); // e.g. "Tomat Musim Hujan"
            $table->foreignId('garden_id')->constrained('gardens')->onDelete('cascade');
            $table->foreignId('device_id')->nullable()->constrained('devices')->onDelete('set null');
            $table->date('tanggal_tanam')->nullable();
            $table->date('estimasi_panen')->nullable();
            $table->string('status_fase')->default('Persiapan'); // Persiapan, Vegetatif, Generatif, Panen
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plantings');
    }
};
