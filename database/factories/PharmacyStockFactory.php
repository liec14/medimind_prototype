<?php

namespace Database\Factories;

use App\Models\CatalogMedicine;
use App\Models\PharmacyStock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PharmacyStock> */
class PharmacyStockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pharmacy_id' => fn () => User::factory()->pharmacy()->create()->pharmacy->id,
            'catalog_medicine_id' => CatalogMedicine::factory(),
            'price' => fake()->randomFloat(2, 2, 200),
            'in_stock' => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(['in_stock' => false]);
    }
}
