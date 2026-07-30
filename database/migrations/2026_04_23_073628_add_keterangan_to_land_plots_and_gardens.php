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
        Schema::table('land_plots', function (Blueprint $table) {
            $table->text('keterangan')->nullable()->after('plant_types');
        });

        Schema::table('gardens', function (Blueprint $table) {
            $table->text('keterangan')->nullable()->after('plant_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('land_plots', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });

        Schema::table('gardens', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });
    }
};
