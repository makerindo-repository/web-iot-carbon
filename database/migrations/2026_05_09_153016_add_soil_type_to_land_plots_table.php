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
        if (! Schema::hasColumn('land_plots', 'soil_type')) {
            Schema::table('land_plots', function (Blueprint $table) {
                $table->string('soil_type')->nullable()->after('area_hectare');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('land_plots', 'soil_type')) {
            Schema::table('land_plots', function (Blueprint $table) {
                $table->dropColumn('soil_type');
            });
        }
    }
};
