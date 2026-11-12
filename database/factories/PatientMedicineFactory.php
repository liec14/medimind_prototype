<?php

namespace Database\Factories;

use App\Models\PatientMedicine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientMedicine> */
class PatientMedicineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => User::factory(),
            'name' => fake()->word().'ol',
            'dosage' => '500 mg, 1 tablet',
            'start_date' => now()->subWeek()->toDateString(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
