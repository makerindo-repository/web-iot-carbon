<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel untuk menyimpan hasil prediksi model AI Forecasting.
 *
 * Setiap baris menyimpan satu prediksi dari satu model untuk satu target
 * pada satu horizon waktu tertentu. Data ini diisi oleh background job
 * (ProcessAiForecast) dan dibaca oleh frontend dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_forecast_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');

            // Target prediksi: "Soil Moisture (%)" atau "pH"
            $table->string('target_metric', 50);

            // Horizon prediksi dalam jam: 1, 6, atau 24
            $table->unsignedSmallInteger('horizon_hours');

            // Model yang digunakan: "svm", "xgboost", atau "lstm"
            $table->string('model_used', 20);

            // Nilai prediksi dari model
            $table->decimal('predicted_value', 10, 4);

            // Nilai aktual sensor saat ini (untuk referensi)
            $table->decimal('current_value', 10, 4)->nullable();

            // Waktu target prediksi (kapan prediksi ini seharusnya terjadi)
            $table->dateTime('predicted_for');

            // Waktu input data terakhir yang digunakan untuk prediksi
            $table->dateTime('input_reading_time');

            // Status: "pending", "completed", "failed"
            $table->string('status', 20)->default('completed');

            // Confidence / catatan error jika gagal
            $table->text('error_message')->nullable();

            $table->timestamps();

            // Index untuk query dashboard: ambil prediksi terbaru per device+target
            $table->index(['device_id', 'target_metric', 'horizon_hours', 'model_used'], 'idx_forecast_lookup');
            $table->index('predicted_for', 'idx_forecast_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_forecast_results');
    }
};
