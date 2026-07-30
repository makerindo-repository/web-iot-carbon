<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index performa untuk iot_readings (tabel time-series, tumbuh cepat).
     *
     * - (device_id, reading_time): melayani query device-scoped bertingkat
     *   waktu — AiInsightController (WHERE device_id=? AND reading_time>=?),
     *   pengambilan reading terbaru per device, dan getReadings ber-filter device.
     * - (reading_time): melayani polling global getReadings
     *   (ORDER BY reading_time DESC LIMIT n) yang dipanggil tiap 30 dtk.
     *
     * device_id sendiri sudah ter-index otomatis oleh foreign key.
     */
    public function up(): void
    {
        Schema::table('iot_readings', function (Blueprint $table) {
            $table->index(['device_id', 'reading_time'], 'iot_readings_device_time_idx');
            $table->index('reading_time', 'iot_readings_reading_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('iot_readings', function (Blueprint $table) {
            $table->dropIndex('iot_readings_device_time_idx');
            $table->dropIndex('iot_readings_reading_time_idx');
        });
    }
};
