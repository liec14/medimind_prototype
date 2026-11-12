<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('contact', 50)->nullable();
            $table->timestamps();
        });

        // Patient <-> doctor link. The patient invites, the doctor accepts.
        Schema::create('doctor_patient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending|active|declined
            $table->timestamps();
            $table->unique(['doctor_id', 'patient_id']);
        });

        // Patient opts in to sharing limited medication info with a pharmacy.
        Schema::create('pharmacy_patient_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('granted_at')->useCurrent();
            $table->unique(['pharmacy_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_patient_consents');
        Schema::dropIfExists('doctor_patient');
        Schema::dropIfExists('pharmacies');
    }
};
