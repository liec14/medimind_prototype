<?php

use App\Models\DoseLog;
use App\Models\PatientMedicine;
use App\Models\User;
use App\Services\AdherenceService;
use App\Services\RefillService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

function doseOn(PatientMedicine $medicine, int $daysAgo, string $time, string $status): DoseLog
{
    return DoseLog::factory()->create([
        'patient_medicine_id' => $medicine->id,
        'scheduled_at' => CarbonImmutable::now()->subDays($daysAgo)->setTimeFromTimeString($time),
        'status' => $status,
        'confirmed_at' => $status === 'taken' ? CarbonImmutable::now()->subDays($daysAgo)->setTimeFromTimeString($time)->addMinutes(5) : null,
    ]);
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00'));
    $this->doctor = User::factory()->doctor()->create();
    $this->patient = User::factory()->patient()->create(['name' => 'Lola Maria Cruz']);
    $this->patient->doctors()->attach($this->doctor->id, ['status' => 'active']);
    $this->medicine = PatientMedicine::factory()->for($this->patient, 'patient')->create(['name' => 'Metformin', 'start_date' => '2026-01-01']);
});

it('computes adherence for many patients in one go', function () {
    $other = User::factory()->patient()->create();
    $otherMedicine = PatientMedicine::factory()->for($other, 'patient')->create();
    $quiet = User::factory()->patient()->create();
    doseOn($this->medicine, 1, '08:00', 'taken');
    doseOn($this->medicine, 2, '08:00', 'taken');
    doseOn($this->medicine, 3, '08:00', 'missed');
    doseOn($otherMedicine, 1, '08:00', 'missed');
    doseOn($this->medicine, 40, '08:00', 'missed'); // outside the window

    $result = app(AdherenceService::class)->summariesFor([$this->patient->id, $other->id, $quiet->id], now()->subDays(13)->startOfDay(), now());

    expect($result[$this->patient->id])->toMatchArray(['taken' => 2, 'missed' => 1, 'rate' => 66.7])
        ->and($result[$other->id])->toMatchArray(['taken' => 0, 'missed' => 1, 'rate' => 0.0])
        ->and($result[$quiet->id])->toMatchArray(['taken' => 0, 'missed' => 0, 'rate' => null]);
});

it('shows summary totals and each patient\'s 14-day adherence on the doctor dashboard', function () {
    doseOn($this->medicine, 1, '08:00', 'taken');
    doseOn($this->medicine, 2, '08:00', 'missed');
    $new = User::factory()->patient()->create(['name' => 'Newbie Nina']);
    $new->doctors()->attach($this->doctor->id, ['status' => 'active']);

    $this->actingAs($this->doctor)->get(route('doctor.dashboard'))
        ->assertOk()->assertSee('Linked patients')->assertSee('At high risk')->assertSee('Open alerts')
        ->assertSee('50% taken (14 days)')->assertSee('No doses yet')
        ->assertViewHas('totals', ['patients' => 2, 'high' => 0, 'alerts' => 0]);
});

it('shows rates, the trend chart and recent doses on the patient page', function () {
    doseOn($this->medicine, 1, '08:00', 'taken');
    doseOn($this->medicine, 2, '08:00', 'taken');
    doseOn($this->medicine, 3, '08:00', 'missed');
    DoseLog::factory()->create(['patient_medicine_id' => $this->medicine->id, 'scheduled_at' => now()->addDay()]); // future: not "recent"

    $page = $this->actingAs($this->doctor)->get(route('doctor.patients.show', $this->patient));

    $page->assertOk()->assertSee('Taken, last 14 days')->assertSee('66.7%')->assertSee('Adherence over time')->assertSee('Doses taken, by week')
        ->assertSee('Recent doses')->assertSee('Full intake history');
    expect($page->viewData('recentDoses'))->toHaveCount(3);
});

it('lets a linked doctor read the full intake history with filters', function () {
    $other = PatientMedicine::factory()->for($this->patient, 'patient')->create(['name' => 'Losartan', 'start_date' => '2026-01-01']);
    doseOn($this->medicine, 1, '08:00', 'taken');
    doseOn($other, 1, '09:00', 'missed');
    doseOn($this->medicine, 60, '08:00', 'missed');

    $url = route('doctor.patients.history', $this->patient);

    $this->actingAs($this->doctor)->get($url)->assertOk()->assertSee('Intake history: Lola Maria Cruz')->assertSee('Read only')->assertSee('Doses taken, by week');
    expect($this->actingAs($this->doctor)->get($url)->viewData('doses'))->toHaveCount(2);
    expect($this->actingAs($this->doctor)->get($url.'?status=missed')->viewData('doses'))->toHaveCount(1);
    expect($this->actingAs($this->doctor)->get($url.'?range=all&status=missed')->viewData('doses'))->toHaveCount(2);
    expect($this->actingAs($this->doctor)->get($url.'?medicine_id='.$other->id)->viewData('doses'))->toHaveCount(1);
    $this->actingAs($this->doctor)->get($url.'?status=bogus')->assertSessionHasErrors('status');
});

it('keeps history away from unlinked doctors, pending links, pharmacies and other patients', function () {
    $stranger = User::factory()->doctor()->create();
    $pending = User::factory()->doctor()->create();
    $this->patient->doctors()->attach($pending->id, ['status' => 'pending']);
    $pharmacy = User::factory()->pharmacy()->create();
    $this->patient->consentedPharmacies()->attach($pharmacy->pharmacy->id);

    foreach ([$stranger, $pending] as $doctor) {
        $this->actingAs($doctor)->get(route('doctor.patients.history', $this->patient))->assertForbidden();
    }
    $this->actingAs($pharmacy)->get(route('doctor.patients.history', $this->patient))->assertForbidden();
    $this->actingAs(User::factory()->patient()->create())->get(route('doctor.patients.history', $this->patient))->assertForbidden();
});

it('does not let a doctor read history through a patient they are not linked to', function () {
    $otherPatient = User::factory()->patient()->create();

    $this->actingAs($this->doctor)->get(route('doctor.patients.history', $otherPatient))->assertForbidden();
});

it('seeds a demo patient who shares refill dates with the first pharmacy', function () {
    $this->seed(DatabaseSeeder::class);

    $pharmacy = User::firstWhere('email', 'pharmacy@medimind.test')->pharmacy;
    $rows = app(RefillService::class)->dueFor($pharmacy);

    expect($rows->pluck('medicine.name')->all())->toBe(['Amlodipine', 'Metformin'])
        ->and($rows->pluck('status')->all())->toBe(['overdue', 'due_soon'])
        ->and(app(RefillService::class)->dueFor(User::firstWhere('email', 'pharmacy2@medimind.test')->pharmacy))->toBeEmpty();
});
