<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add carbon stock storage used by the AgriSense carbon methodology.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('land_plots', function (Blueprint $table) {
            if (! Schema::hasColumn('land_plots', 'soc_baseline_gc_m2')) {
                $table->decimal('soc_baseline_gc_m2', 12, 4)->default(0)
                    ->after('area_hectare')
                    ->comment('SOC baseline from SoilGrids 2.0 (gC/m2, 0-30cm)');
            }

            if (! Schema::hasColumn('land_plots', 'c_max_gc_m2')) {
                $table->decimal('c_max_gc_m2', 12, 4)->default(0)
                    ->after('soc_baseline_gc_m2')
                    ->comment('Maximum modeled carbon storage capacity (gC/m2)');
            }

            if (! Schema::hasColumn('land_plots', 'soc_source')) {
                $table->string('soc_source', 100)->nullable()
                    ->after('c_max_gc_m2')
                    ->comment('SOC source: SoilGrids 2.0 / Manual / Fallback');
            }
        });

        if (! Schema::hasTable('carbon_daily_stocks')) {
            Schema::create('carbon_daily_stocks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('device_id');
                $table->date('stock_date');
                $table->decimal('daily_gpp_gc_m2', 12, 6)->default(0)->comment('Daily GPP (gC/m2)');
                $table->decimal('daily_reco_gc_m2', 12, 6)->default(0)->comment('Daily RECO (gC/m2)');
                $table->decimal('daily_npp_gc_m2', 12, 6)->default(0)->comment('Daily NPP = GPP - Ra (gC/m2)');
                $table->decimal('daily_nee_gc_m2', 12, 6)->default(0)->comment('Daily NEE = GPP - RECO (gC/m2)');
                $table->decimal('cumulative_npp_gc_m2', 12, 4)->default(0)->comment('Accumulated NPP as biomass carbon (gC/m2)');
                $table->integer('readings_count')->default(0);
                $table->timestamps();

                $table->unique(['device_id', 'stock_date']);
                $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
                $table->index('stock_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('carbon_daily_stocks');

        Schema::table('land_plots', function (Blueprint $table) {
            $columnsToDrop = array_values(array_filter([
                Schema::hasColumn('land_plots', 'soc_baseline_gc_m2') ? 'soc_baseline_gc_m2' : null,
                Schema::hasColumn('land_plots', 'c_max_gc_m2') ? 'c_max_gc_m2' : null,
                Schema::hasColumn('land_plots', 'soc_source') ? 'soc_source' : null,
            ]));

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
