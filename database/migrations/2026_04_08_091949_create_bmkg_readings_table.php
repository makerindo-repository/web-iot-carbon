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
        Schema::create('bmkg_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plot_id')->nullable()->constrained('land_plots')->onDelete('cascade');
            $table->string('station_id', 50)->nullable();
            $table->string('station_name', 150)->nullable();
            $table->string('area_name', 150)->nullable();
            $table->dateTime('timestamp_bmkg');
            $table->decimal('air_temperature_c', 5, 2);
            $table->decimal('air_humidity_percent', 5, 2);
            $table->decimal('rainfall_mm', 8, 2)->default(0);
            $table->decimal('wind_speed_mps', 6, 2);
            $table->decimal('wind_direction_deg', 6, 2);
            $table->decimal('air_pressure_hpa', 7, 2);
            $table->string('weather_status', 100)->nullable();
            $table->string('source_api', 100)->nullable();
            $table->timestamp('reference_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bmkg_readings');
    }
};
