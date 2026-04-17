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
        Schema::create('land_plots', function (Blueprint $table) {
            $table->id();
            $table->string('plot_code');
            $table->string('plot_name');
            $table->string('owner_name')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->json('polygon')->nullable();
            $table->decimal('area_hectare', 8, 2);
            $table->string('soil_type')->nullable();
            $table->text('plant_types')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('land_plots');
    }
};
