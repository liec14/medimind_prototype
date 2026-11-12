<?php

use App\Models\CatalogMedicine;
use App\Models\PatientMedicine;
use App\Models\User;
use App\Services\InteractionService;
use Database\Seeders\CatalogSeeder;

function cat(string $generic): CatalogMedicine
{
    return CatalogMedicine::where('generic_name', $generic)->firstOrFail();
}

function takes(User $patient, string $generic, array $attrs = []): PatientMedicine
{
    return PatientMedicine::factory()->for($patient, 'patient')->create($attrs + [
        'catalog_medicine_id' => cat($generic)->id,
        'name' => $generic,
    ]);
}

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    $this->patient = User::factory()->patient()->create();
    $this->service = app(InteractionService::class);
});

it('finds interactions among the active medicines and lists the high ones first', function () {
    takes($this->patient, 'Ibuprofen');
    takes($this->patient, 'Aspirin');
    expect($this->service->forPatient($this->patient))->toHaveCount(1)
        ->and($this->service->forPatient($this->patient)->first()['severity'])->toBe('moderate');

    takes($this->patient, 'Naproxen sodium');
    $found = $this->service->forPatient($this->patient);

    // Ibuprofen+Aspirin (moderate) and Ibuprofen+Naproxen (high). Aspirin+Naproxen is not in the catalog.
    expect($found)->toHaveCount(2)
        ->and($found->first()['severity'])->toBe('high')
        ->and($found->first()['medicines'])->toContain('Ibuprofen', 'Naproxen sodium');
});

it('ignores inactive medicines, custom medicines and single medicines', function () {
    expect($this->service->forPatient($this->patient))->toBeEmpty();

    takes($this->patient, 'Ibuprofen');
    expect($this->service->forPatient($this->patient))->toBeEmpty();

    takes($this->patient, 'Aspirin', ['active' => false]);
    PatientMedicine::factory()->for($this->patient, 'patient')->create(['name' => 'Herbal tea', 'catalog_medicine_id' => null]);
    expect($this->service->forPatient($this->patient))->toBeEmpty();
});

it('checks one medicine against others whichever way the pair is stored', function () {
    expect($this->service->between(cat('Aspirin')->id, [cat('Ibuprofen')->id]))->toHaveCount(1)
        ->and($this->service->between(cat('Ibuprofen')->id, [cat('Aspirin')->id]))->toHaveCount(1)
        ->and($this->service->between(cat('Ibuprofen')->id, [cat('Paracetamol')->id]))->toBeEmpty()
        ->and($this->service->between(cat('Ibuprofen')->id, [cat('Ibuprofen')->id]))->toBeEmpty()
        ->and($this->service->between(cat('Ibuprofen')->id, []))->toBeEmpty();
});

it('checks a just-saved medicine against the rest of the list', function () {
    takes($this->patient, 'Ibuprofen');
    $new = takes($this->patient, 'Losartan');

    expect($this->service->forMedicine($new))->toHaveCount(1);
    expect($this->service->forMedicine($new->replicate()->forceFill(['active' => false])))->toBeEmpty();
});

it('flags an interaction on the medicine list right after adding a medicine', function () {
    takes($this->patient, 'Ibuprofen');

    $this->actingAs($this->patient)->post('/patient/medicines', [
        'name' => 'Losartan', 'dosage' => '50 mg', 'start_date' => '2026-11-01', 'active' => '1',
    ])->assertRedirect(route('patient.medicines.index'))->assertSessionHas('new_interactions', 1);

    $this->actingAs($this->patient)->followingRedirects()->get(route('patient.medicines.index'))
        ->assertSee('Possible interactions in your list')->assertSee('Ibuprofen + Losartan');
});

it('shows interactions to the linked doctor but not on a clean list', function () {
    $doctor = User::factory()->doctor()->create();
    $this->patient->doctors()->attach($doctor->id, ['status' => 'active']);

    $this->actingAs($doctor)->get(route('doctor.patients.show', $this->patient))->assertDontSee('Possible interactions');

    takes($this->patient, 'Atenolol');
    takes($this->patient, 'Metoprolol');
    $this->actingAs($doctor)->get(route('doctor.patients.show', $this->patient))
        ->assertSee('Metoprolol + Atenolol')->assertSee('Avoid taking together');
});
