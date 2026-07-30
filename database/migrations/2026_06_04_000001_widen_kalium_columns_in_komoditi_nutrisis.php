<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen kalium columns from decimal(5,2) to decimal(8,2)
     * to safely accommodate values > 999 ppm.
     */
    public function up(): void
    {
        Schema::table('komoditi_nutrisis', function (Blueprint $table) {
            $table->decimal('kalium_min', 8, 2)->nullable()->change();
            $table->decimal('kalium_max', 8, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('komoditi_nutrisis', function (Blueprint $table) {
            $table->decimal('kalium_min', 5, 2)->nullable()->change();
            $table->decimal('kalium_max', 5, 2)->nullable()->change();
        });
    }
};
