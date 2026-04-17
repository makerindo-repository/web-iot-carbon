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
        Schema::create('iot_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->foreignId('plot_id')->constrained('land_plots')->onDelete('cascade');
            $table->dateTime('reading_time');
            $table->decimal('air_temperature_sensor', 8, 2);
            $table->decimal('air_humidity_sensor', 8, 2);
            $table->decimal('soil_temperature', 8, 2);
            $table->decimal('soil_moisture', 8, 2);
            $table->decimal('soil_ph', 8, 2);
            $table->decimal('co2_sensor', 10, 2);
            $table->decimal('soil_organic_carbon', 10, 4);
            $table->decimal('carbon_flux', 10, 4);
            $table->json('samples')->nullable();
            $table->integer('signal_strength')->nullable();
            $table->boolean('data_valid')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iot_readings');
    }
};
