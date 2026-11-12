<?php

namespace Database\Factories;

use App\Models\TrustedContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrustedContact> */
class TrustedContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => User::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'relationship' => 'Daughter',
            'notify' => true,
        ];
    }
}
