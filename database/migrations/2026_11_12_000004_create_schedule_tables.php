<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Typical day, used to suggest sensible dose times. Times are "HH:MM".
        Schema::create('patient_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('wake_time', 5)->default('07:00');
            $table->string('breakfast_time', 5)->default('08:00');
            $table->string('lunch_time', 5)->default('12:00');
            $table->string('dinner_time', 5)->default('18:30');
            $table->string('sleep_time', 5)->default('22:00');
            $table->timestamps();
        });

        // One schedule per registered medicine. Start/end dates come from the medicine.
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_medicine_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('frequency', 30); // once_daily|twice_daily|three_times_daily|four_times_daily|custom
            $table->json('times');           // ["08:00", "20:00"]
            $table->json('days_of_week')->nullable(); // null = every day; else [0..6], 0 = Sunday
            $table->timestamps();
        });

        // One row per scheduled dose. This is the data adherence is computed from.
        Schema::create('dose_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('status', 20)->default('pending'); // pending|taken|missed
            $table->dateTime('confirmed_at')->nullable();
            $table->unsignedSmallInteger('snoozed_count')->default(0);
            $table->timestamps();
            $table->unique(['patient_medicine_id', 'scheduled_at']);
            $table->index(['patient_id', 'scheduled_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dose_logs');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('patient_routines');
    }
};
