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
        Schema::table('gardens', function (Blueprint $table) {
            $table->date('tanggal_tanam')->nullable()->after('plant_types');
            $table->string('fase_tanam_saat_ini')->nullable()->after('tanggal_tanam');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gardens', function (Blueprint $table) {
            $table->dropColumn(['tanggal_tanam', 'fase_tanam_saat_ini']);
        });
    }
};
