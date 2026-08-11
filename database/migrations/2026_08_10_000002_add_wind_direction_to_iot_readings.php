<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iot_readings', function (Blueprint $table) {
            $table->integer('wind_direction_deg')->nullable()->after('wind_speed_kmh');
        });
    }

    public function down(): void
    {
        Schema::table('iot_readings', function (Blueprint $table) {
            $table->dropColumn('wind_direction_deg');
        });
    }
};
