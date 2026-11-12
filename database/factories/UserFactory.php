<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Patient,
            'phone' => fake()->numerify('09#########'),
            'birthdate' => fake()->dateTimeBetween('-80 years', '-18 years'),
            'approved_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function patient(): static
    {
        return $this->state(['role' => Role::Patient]);
    }

    public function doctor(): static
    {
        return $this->state(['role' => Role::Doctor, 'license_no' => fake()->numerify('PRC-#######')]);
    }

    public function pharmacy(): static
    {
        return $this->state(['role' => Role::Pharmacy, 'license_no' => fake()->numerify('PRC-#######')])
            ->afterCreating(fn (User $user) => Pharmacy::create([
                'user_id' => $user->id,
                'name' => fake()->company().' Pharmacy',
                'address' => fake()->address(),
            ]));
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function pending(): static
    {
        return $this->state(['approved_at' => null]);
    }
}
