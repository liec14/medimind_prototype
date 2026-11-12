<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What each registered pharmacy carries, with its price and availability.
        Schema::create('pharmacy_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_medicine_id')->constrained('medicines_catalog')->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->boolean('in_stock')->default(true);
            $table->timestamps(); // updated_at doubles as "price last confirmed"
            $table->unique(['pharmacy_id', 'catalog_medicine_id']);
        });

        // Which OTC medicines are suggested for which everyday complaint.
        Schema::create('otc_rules', function (Blueprint $table) {
            $table->id();
            $table->string('symptom');                 // label shown to patients, e.g. "Headache"
            $table->string('keywords')->nullable();    // comma list for free-text matching
            $table->foreignId('catalog_medicine_id')->constrained('medicines_catalog')->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(1); // 1 = first choice
            $table->text('notes')->nullable();         // short tip for this medicine
            $table->text('see_doctor_if')->nullable(); // red flags for this complaint
            $table->timestamps();
            $table->unique(['symptom', 'catalog_medicine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otc_rules');
        Schema::dropIfExists('pharmacy_stock');
    }
};
