<?php

use App\Models\PatientMedicine;
use App\Models\User;
use Database\Seeders\CatalogSeeder;

it('renders the medicine pages for a patient', function () {
    $this->seed(CatalogSeeder::class);
    $patient = User::factory()->patient()->create();
    $medicine = PatientMedicine::factory()->for($patient, 'patient')->create();

    $this->actingAs($patient)->get(route('patient.medicines.index'))->assertOk()->assertSee($medicine->name);
    $this->actingAs($patient)->get(route('patient.medicines.create'))->assertOk()->assertSee('Biogesic');
    $this->actingAs($patient)->get(route('patient.medicines.edit', $medicine))->assertOk()->assertSee($medicine->name);
});

it('renders the doctor review page for a linked doctor', function () {
    $patient = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();
    $patient->doctors()->attach($doctor->id, ['status' => 'active']);
    $medicine = PatientMedicine::factory()->for($patient, 'patient')->create();

    $this->actingAs($doctor)->get(route('doctor.medicines.edit', [$patient, $medicine]))->assertOk()->assertSee('Review');
});
