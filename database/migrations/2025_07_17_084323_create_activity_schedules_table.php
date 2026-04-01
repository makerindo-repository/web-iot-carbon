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
        Schema::create('activity_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('day');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('agenda');
            $table->timestamps();
        });

        // Pivot table for many-to-many relationship between activity schedules and lecturers
        Schema::create('activity_schedules_lecturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_schedule_id')->constrained()->onDelete('cascade');
            $table->foreignId('lecturer_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Pivot table for many-to-many relationship between activity schedules and students
        Schema::create('activity_schedules_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_schedule_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_schedules');
        Schema::dropIfExists('activity_schedules_lecturers');
        Schema::dropIfExists('activity_schedules_students');
    }
};
