<?php

namespace Database\Factories;

use App\Models\PatientMedicine;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Schedule> */
class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_medicine_id' => PatientMedicine::factory(),
            'frequency' => 'twice_daily',
            'times' => ['08:00', '20:00'],
            'days_of_week' => null,
        ];
    }
}
