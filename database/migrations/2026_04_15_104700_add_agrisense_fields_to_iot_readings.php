<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iot_readings', function (Blueprint $table) {
            // Field lokasi per TOR
            $table->decimal('latitude', 10, 7)->nullable()->after('plot_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('altitude_m', 8, 2)->nullable()->after('longitude');
            // Field karbon tambahan
            $table->decimal('tvoc_ppb', 8, 2)->nullable()->after('co2_sensor');
            // Field lingkungan tambahan
            $table->decimal('air_pressure_hpa', 8, 2)->nullable()->after('air_humidity_sensor');
            $table->decimal('light_lux', 10, 2)->nullable()->after('air_pressure_hpa');
            // Field tanah tambahan (7-in-1)
            $table->decimal('soil_ec_ms_cm', 8, 3)->nullable()->after('soil_ph');
            $table->decimal('soil_n_mg_kg', 8, 2)->nullable()->after('soil_ec_ms_cm');
            $table->decimal('soil_p_mg_kg', 8, 2)->nullable()->after('soil_n_mg_kg');
            $table->decimal('soil_k_mg_kg', 8, 2)->nullable()->after('soil_p_mg_kg');
            // Field daya
            $table->decimal('battery_voltage', 4, 2)->nullable()->after('soil_k_mg_kg');
            $table->integer('battery_percent')->nullable()->after('battery_voltage');
            // Field komunikasi & status
            $table->string('network_type', 20)->nullable()->after('signal_strength');
            $table->string('message_id', 64)->nullable()->unique()->after('id');
            $table->string('node_status', 20)->nullable()->after('network_type');
            $table->string('sensor_status', 20)->nullable()->after('node_status');
            $table->string('firmware_version', 20)->nullable()->after('sensor_status');
        });
    }

    public function down(): void
    {
        Schema::table('iot_readings', function (Blueprint $table) {
            $table->dropColumn([
                'latitude', 'longitude', 'altitude_m', 'tvoc_ppb',
                'air_pressure_hpa', 'light_lux', 'soil_ec_ms_cm',
                'soil_n_mg_kg', 'soil_p_mg_kg', 'soil_k_mg_kg',
                'battery_voltage', 'battery_percent', 'network_type',
                'message_id', 'node_status', 'sensor_status', 'firmware_version'
            ]);
        });
    }
};
