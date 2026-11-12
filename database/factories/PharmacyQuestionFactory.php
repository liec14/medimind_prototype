<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use App\Models\PharmacyQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PharmacyQuestion> */
class PharmacyQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pharmacy_id' => fn () => User::factory()->pharmacy()->create()->pharmacy->id,
            'patient_id' => User::factory(),
            'question' => 'Can I take this medicine together with paracetamol?',
        ];
    }

    public function answered(): static
    {
        return $this->state(['answer' => 'Yes, that is usually fine. Keep to the label dose.', 'answered_at' => now()]);
    }

    public function for_pharmacy(Pharmacy $pharmacy): static
    {
        return $this->state(['pharmacy_id' => $pharmacy->id]);
    }
}
