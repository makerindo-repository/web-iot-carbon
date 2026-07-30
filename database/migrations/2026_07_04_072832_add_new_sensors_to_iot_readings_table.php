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
        Schema::table('iot_readings', function (Blueprint $table) {
            $table->decimal('ch4_ppm', 10, 4)->nullable()->after('tvoc_ppb');
            $table->decimal('no2_ppb', 10, 4)->nullable()->after('ch4_ppm');
            $table->decimal('n2o_ppb', 10, 4)->nullable()->after('no2_ppb');
            $table->decimal('cloud_cover_percent', 5, 2)->nullable()->after('air_pressure_hpa');
            $table->decimal('wind_speed_kmh', 6, 2)->nullable()->after('cloud_cover_percent');
            $table->string('ip_address', 45)->nullable()->after('firmware_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iot_readings', function (Blueprint $table) {
            $table->dropColumn([
                'ch4_ppm',
                'no2_ppb',
                'n2o_ppb',
                'cloud_cover_percent',
                'wind_speed_kmh',
                'ip_address',
            ]);
        });
    }
};
