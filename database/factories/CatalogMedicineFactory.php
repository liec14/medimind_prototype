<?php

namespace Database\Factories;

use App\Models\CatalogMedicine;
use App\Models\MedicineCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CatalogMedicine> */
class CatalogMedicineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'generic_name' => fake()->unique()->word().'ol',
            'category_id' => MedicineCategory::factory(),
            'form' => 'tablet',
            'strength' => '500 mg',
            'purpose' => fake()->sentence(),
            'is_otc' => false,
        ];
    }
}
