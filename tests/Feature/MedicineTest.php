<?php

use App\Models\CatalogMedicine;
use App\Models\DrugInteraction;
use App\Models\MedicineCategory;
use App\Models\PatientMedicine;
use App\Models\User;
use Database\Seeders\CatalogSeeder;

function medicinePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Custom Remedy',
        'dosage' => '1 tablet',
        'start_date' => '2026-11-01',
        'active' => '1',
    ], $overrides);
}

it('lets a patient register a custom medicine', function () {
    $patient = User::factory()->patient()->create();
    $category = MedicineCategory::factory()->create();

    $this->actingAs($patient)->post('/patient/medicines', medicinePayload(['category_id' => $category->id]))
        ->assertRedirect(route('patient.medicines.index'));

    $medicine = $patient->medicines()->first();
    expect($medicine->name)->toBe('Custom Remedy')
        ->and($medicine->category_id)->toBe($category->id)
        ->and($medicine->catalog_medicine_id)->toBeNull()
        ->and($medicine->entered_by)->toBe($patient->id);
});

it('links a catalog medicine and fills category and purpose when blank', function () {
    $patient = User::factory()->patient()->create();
    $catalog = CatalogMedicine::factory()->create(['generic_name' => 'Metformin', 'purpose' => 'Lowers blood sugar.']);

    $this->actingAs($patient)->post('/patient/medicines', medicinePayload(['name' => 'metformin']));

    $medicine = $patient->medicines()->first();
    expect($medicine->catalog_medicine_id)->toBe($catalog->id)
        ->and($medicine->name)->toBe('Metformin')
        ->and($medicine->category_id)->toBe($catalog->category_id)
        ->and($medicine->purpose)->toBe('Lowers blood sugar.');
});

it('validates medicine input', function () {
    $patient = User::factory()->patient()->create();

    $this->actingAs($patient)->post('/patient/medicines', ['name' => ''])
        ->assertSessionHasErrors(['name', 'dosage', 'start_date']);

    $this->actingAs($patient)->post('/patient/medicines', medicinePayload(['end_date' => '2026-10-01']))
        ->assertSessionHasErrors('end_date');

    expect(PatientMedicine::count())->toBe(0);
});

it('lets a patient edit, deactivate and delete their own medicine', function () {
    $patient = User::factory()->patient()->create();
    $medicine = PatientMedicine::factory()->for($patient, 'patient')->create();

    $this->actingAs($patient)->put(route('patient.medicines.update', $medicine), medicinePayload(['name' => 'Renamed', 'active' => '0']));
    expect($medicine->fresh())->name->toBe('Renamed')->active->toBeFalse()->updated_by->toBe($patient->id);

    $this->actingAs($patient)->delete(route('patient.medicines.destroy', $medicine));
    expect(PatientMedicine::count())->toBe(0);
});

it('lists only the patient\'s own medicines', function () {
    $patient = User::factory()->patient()->create();
    PatientMedicine::factory()->for($patient, 'patient')->create(['name' => 'Mine']);
    PatientMedicine::factory()->create(['name' => 'Someone Elses']);

    $this->actingAs($patient)->get('/patient/medicines')->assertSee('Mine')->assertDontSee('Someone Elses');
});

it('stops a patient from touching another patient\'s medicine', function () {
    $patient = User::factory()->patient()->create();
    $others = PatientMedicine::factory()->create();

    $this->actingAs($patient)->get(route('patient.medicines.edit', $others))->assertForbidden();
    $this->actingAs($patient)->put(route('patient.medicines.update', $others), medicinePayload())->assertForbidden();
    $this->actingAs($patient)->delete(route('patient.medicines.destroy', $others))->assertForbidden();
    expect(PatientMedicine::count())->toBe(1);
});

it('lets a linked doctor correct a medicine and records who did', function () {
    $patient = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();
    $patient->doctors()->attach($doctor->id, ['status' => 'active']);
    $medicine = PatientMedicine::factory()->for($patient, 'patient')->create(['dosage' => '10 mg']);

    $this->actingAs($doctor)->get(route('doctor.patients.show', $patient))->assertSee($medicine->name);
    $this->actingAs($doctor)->put(route('doctor.medicines.update', [$patient, $medicine]), medicinePayload(['name' => $medicine->name, 'dosage' => '5 mg']))
        ->assertRedirect(route('doctor.patients.show', $patient));

    expect($medicine->fresh())->dosage->toBe('5 mg')->updated_by->toBe($doctor->id);
});

it('blocks unlinked doctors and doctors editing through the wrong patient', function () {
    $patient = User::factory()->patient()->create();
    $medicine = PatientMedicine::factory()->for($patient, 'patient')->create();
    $stranger = User::factory()->doctor()->create();

    $this->actingAs($stranger)->get(route('doctor.medicines.edit', [$patient, $medicine]))->assertForbidden();
    $this->actingAs($stranger)->put(route('doctor.medicines.update', [$patient, $medicine]), medicinePayload())->assertForbidden();

    $linked = User::factory()->doctor()->create();
    $otherPatient = User::factory()->patient()->create();
    $otherPatient->doctors()->attach($linked->id, ['status' => 'active']);
    $this->actingAs($linked)->get(route('doctor.medicines.edit', [$otherPatient, $medicine]))->assertNotFound();
});

it('does not let doctors delete a patient\'s medicine', function () {
    $patient = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();
    $patient->doctors()->attach($doctor->id, ['status' => 'active']);
    $medicine = PatientMedicine::factory()->for($patient, 'patient')->create();

    expect($doctor->can('delete', $medicine))->toBeFalse()->and($doctor->can('update', $medicine))->toBeTrue();
});

it('seeds a reference catalog with categories and interactions', function () {
    $this->seed(CatalogSeeder::class);

    expect(CatalogMedicine::count())->toBeGreaterThanOrEqual(35)
        ->and(CatalogMedicine::whereNull('category_id')->count())->toBe(0)
        ->and(CatalogMedicine::findByName('PARACETAMOL'))->not->toBeNull()
        ->and(DrugInteraction::count())->toBeGreaterThan(5);

    $this->seed(CatalogSeeder::class); // idempotent
    expect(CatalogMedicine::where('generic_name', 'Paracetamol')->count())->toBe(1);
});
