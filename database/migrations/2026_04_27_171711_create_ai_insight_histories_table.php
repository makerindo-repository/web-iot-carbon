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
        Schema::create('ai_insight_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('time_range', 10);
            $table->text('analysis_text');
            $table->text('recommendation_json'); // Menggunakan text agar lebih fleksibel dibanding JSON
            $table->timestamps();

            // Foreign key to devices table (assuming the table is named devices)
            // But from the context, IotReading uses device_id which maps to devices.
            // Let's add the constraint. If it fails, we can adjust.
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_insight_histories');
    }
};
