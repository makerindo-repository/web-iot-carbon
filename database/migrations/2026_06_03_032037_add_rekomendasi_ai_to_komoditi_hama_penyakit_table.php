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
        Schema::table('komoditi_hama_penyakit', function (Blueprint $table) {
            $table->text('rekomendasi_ai')->nullable()->after('pengendalian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('komoditi_hama_penyakit', function (Blueprint $table) {
            $table->dropColumn('rekomendasi_ai');
        });
    }
};
