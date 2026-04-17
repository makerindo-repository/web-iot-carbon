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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string("device_code");
            $table->foreignId('plot_id')->nullable()->constrained('land_plots')->onDelete('cascade');
            $table->foreignId('garden_id')->nullable()->constrained('gardens')->onDelete('cascade');  
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude',11,8)->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('device_status')->default('offline');
            $table->decimal('altitude', 10, 2)->nullable();
            $table->timestamp('altitude_fetched_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
            Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['altitude', 'altitude_fetched_at']);
        });
    }
};
