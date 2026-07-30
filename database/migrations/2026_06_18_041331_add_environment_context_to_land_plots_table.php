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
        Schema::table('land_plots', function (Blueprint $table) {
            $table->string('kondisi_sekitar')->nullable()->after('keterangan');
            $table->integer('radius_konteks_m')->default(60)->after('kondisi_sekitar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('land_plots', function (Blueprint $table) {
            $table->dropColumn(['kondisi_sekitar', 'radius_konteks_m']);
        });
    }
};
