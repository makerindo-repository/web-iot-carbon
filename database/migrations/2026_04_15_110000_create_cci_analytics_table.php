<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cci_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iot_reading_id')->constrained('iot_readings')->onDelete('cascade');
            $table->string('device_id', 50);
            $table->decimal('cci_value', 8, 3);
            $table->string('cci_status', 30);
            $table->string('model_version', 20)->default('1.0.0');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('device_id');
            $table->index('cci_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cci_analytics');
    }
};
