<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Selaraskan skema land_plots dengan validasi controller.
     *
     * LandPlotController@store/@update memvalidasi latitude, longitude, dan
     * area_hectare sebagai `nullable`, namun kolom aslinya NOT NULL sehingga
     * payload minimal (mis. hanya plot_name) menyebabkan QueryException 500.
     * Membuat kolom nullable = perilaku yang diharapkan (plot boleh dibuat
     * dulu tanpa koordinat/luas; SOC baseline & peta sudah menangani null).
     */
    public function up(): void
    {
        Schema::table('land_plots', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
            $table->decimal('area_hectare', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('land_plots', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable(false)->change();
            $table->decimal('longitude', 10, 7)->nullable(false)->change();
            $table->decimal('area_hectare', 8, 2)->nullable(false)->change();
        });
    }
};
