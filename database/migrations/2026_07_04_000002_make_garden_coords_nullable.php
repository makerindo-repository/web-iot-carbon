<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Selaraskan skema gardens dengan validasi controller (kembaran fix
     * land_plots / B-0). GardenController@store & @update memvalidasi
     * latitude, longitude, dan area_hectare sebagai `nullable`, namun kolom
     * aslinya NOT NULL sehingga POST /gardens tanpa field tersebut memicu
     * QueryException 500. Membuat kolom nullable = perilaku yang diharapkan.
     */
    public function up(): void
    {
        Schema::table('gardens', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
            $table->decimal('area_hectare', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gardens', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable(false)->change();
            $table->decimal('longitude', 10, 7)->nullable(false)->change();
            $table->decimal('area_hectare', 8, 2)->nullable(false)->change();
        });
    }
};
