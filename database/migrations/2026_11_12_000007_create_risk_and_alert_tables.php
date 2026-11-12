<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // People the patient allows MediMind to alert (family, caregiver). No account needed.
        Schema::create('trusted_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('relationship', 50)->nullable();
            $table->boolean('notify')->default(true);
            $table->timestamps();
            $table->index('patient_id');
        });

        // One adherence-risk estimate per patient per day, with the reasons behind the score.
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->date('assessed_on');
            $table->decimal('score', 5, 1);
            $table->string('level', 10); // low|medium|high
            $table->json('factors');
            $table->dateTime('computed_at');
            $table->timestamps();
            $table->unique(['patient_id', 'assessed_on']);
        });

        $this->createAlerts();
        $this->createSuggestions();
    }

    private function createAlerts(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);  // risk|emergency|refill
            $table->string('level', 10); // medium|high
            $table->text('message');
            $table->json('data')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->string('resolution', 30)->nullable(); // resumed|improved|doctor
            $table->timestamps();
            $table->index(['patient_id', 'type', 'resolved_at']);
        });
    }

    private function createSuggestions(): void
    {
        // "You often miss your evening dose: move it earlier?" The patient accepts or dismisses.
        Schema::create('reminder_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_medicine_id')->constrained()->cascadeOnDelete();
            $table->string('slot', 20);
            $table->unsignedSmallInteger('shift_minutes');
            $table->string('status', 20)->default('pending'); // pending|accepted|dismissed
            $table->timestamps();
            $table->unique(['patient_medicine_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_suggestions');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('trusted_contacts');
    }
};
