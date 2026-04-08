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
            $table->foreignId('plot_id')->constrained('land_plots')->onDelete('cascade');
            $table->dateTime('reference_time');
            $table->decimal('air_temperature_bmkg', 8, 2);
            $table->decimal('air_humidity_bmkg', 8, 2);
            $table->decimal('rainfall_mm', 8, 2);
            $table->decimal('wind_speed_bmkg', 8, 2);
            $table->integer('wind_direction');
            $table->decimal('air_pressure', 8, 2);
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
