<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Reference list of known medicines (information shown to patients, OTC rules, pharmacy stock).
        Schema::create('medicines_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('generic_name')->unique();
            $table->string('brand_names')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('medicine_categories')->nullOnDelete();
            $table->string('form', 50)->nullable();
            $table->string('strength', 50)->nullable();
            $table->text('purpose')->nullable();
            $table->text('how_to_take')->nullable();
            $table->text('warnings')->nullable();
            $table->boolean('is_otc')->default(false);
            $table->timestamps();
        });

        // Pair is stored once with medicine_a_id < medicine_b_id.
        Schema::create('drug_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_a_id')->constrained('medicines_catalog')->cascadeOnDelete();
            $table->foreignId('medicine_b_id')->constrained('medicines_catalog')->cascadeOnDelete();
            $table->string('severity', 20); // moderate|high
            $table->text('note');
            $table->timestamps();
            $table->unique(['medicine_a_id', 'medicine_b_id']);
        });

        Schema::create('patient_medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('catalog_medicine_id')->nullable()->constrained('medicines_catalog')->nullOnDelete();
            $table->string('name');
            $table->string('dosage', 100);
            $table->string('purpose')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('medicine_categories')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_medicines');
        Schema::dropIfExists('drug_interactions');
        Schema::dropIfExists('medicines_catalog');
        Schema::dropIfExists('medicine_categories');
    }
};
