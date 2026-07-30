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
        Schema::create('forecast_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->foreignId('source_reading_id')->nullable()->constrained('iot_readings')->onDelete('set null');
            $table->string('model_used', 20); // svm | xgboost | lstm
            $table->string('target_metric', 60); // e.g. "Carbon Flux (NEE AgriSense)"
            $table->unsignedSmallInteger('horizon_hours')->default(0);
            $table->decimal('predicted_value', 14, 4)->nullable();
            $table->dateTime('predicted_for'); // reading_time + horizon_hours
            $table->string('assumptions_version', 20)->default('v1-fixed-baseline');
            $table->json('feature_snapshot')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'target_metric', 'horizon_hours', 'created_at'], 'forecast_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_predictions');
    }
};
