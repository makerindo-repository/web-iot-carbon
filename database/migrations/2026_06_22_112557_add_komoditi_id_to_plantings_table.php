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
        if (! Schema::hasColumn('plantings', 'komoditi_id')) {
            Schema::table('plantings', function (Blueprint $table) {
                $table->foreignId('komoditi_id')->nullable()->after('device_id')->constrained('komoditi_tanaman')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plantings', function (Blueprint $table) {
            $table->dropForeign(['komoditi_id']);
            $table->dropColumn('komoditi_id');
        });
    }
};
