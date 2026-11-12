<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the patient's current supply runs out. Drives refill reminders for the patient's pharmacy.
        Schema::table('patient_medicines', function (Blueprint $table) {
            $table->date('supply_until')->nullable()->after('end_date');
        });

        // A message a pharmacy sent a consenting patient about a refill.
        Schema::create('pharmacy_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->dateTime('sent_at');
            $table->timestamps();
            $table->index(['pharmacy_id', 'patient_id', 'patient_medicine_id'], 'followups_lookup');
        });

        // A patient's safe-use question to a pharmacy, and the pharmacist's answer.
        Schema::create('pharmacy_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->timestamps();
            $table->index(['pharmacy_id', 'answered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_questions');
        Schema::dropIfExists('pharmacy_followups');
        Schema::table('patient_medicines', function (Blueprint $table) {
            $table->dropColumn('supply_until');
        });
    }
};
