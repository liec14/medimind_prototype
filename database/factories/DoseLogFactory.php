<?php

namespace Database\Factories;

use App\Models\DoseLog;
use App\Models\PatientMedicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DoseLog> */
class DoseLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_medicine_id' => PatientMedicine::factory(),
            'patient_id' => fn (array $attrs) => PatientMedicine::find($attrs['patient_medicine_id'])->patient_id,
            'scheduled_at' => now()->addHour(),
            'status' => DoseLog::PENDING,
        ];
    }

    public function taken(): static
    {
        return $this->state(fn (array $a) => ['status' => DoseLog::TAKEN, 'confirmed_at' => $a['scheduled_at']]);
    }

    public function missed(): static
    {
        return $this->state(['status' => DoseLog::MISSED]);
    }
}
