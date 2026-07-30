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
        Schema::create('komoditi_nutrisis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komoditi_id')->constrained('komoditi_tanaman')->onDelete('cascade');
            $table->decimal('nitrogen_min', 5, 2)->nullable();
            $table->decimal('nitrogen_max', 5, 2)->nullable();
            $table->decimal('fosfor_min', 5, 2)->nullable();
            $table->decimal('fosfor_max', 5, 2)->nullable();
            $table->decimal('kalium_min', 5, 2)->nullable();
            $table->decimal('kalium_max', 5, 2)->nullable();
            $table->string('satuan_npk', 50)->nullable(); // ppm, mg/kg, %
            $table->decimal('bahan_organik_min', 5, 2)->nullable();
            $table->decimal('bahan_organik_max', 5, 2)->nullable();
            $table->text('rekomendasi_pemupukan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('komoditi_nutrisis');
    }
};
