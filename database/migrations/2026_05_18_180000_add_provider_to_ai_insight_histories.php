<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_insight_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_insight_histories', 'provider')) {
                $table->string('provider', 100)->nullable()->after('time_range');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_insight_histories', function (Blueprint $table) {
            if (Schema::hasColumn('ai_insight_histories', 'provider')) {
                $table->dropColumn('provider');
            }
        });
    }
};
