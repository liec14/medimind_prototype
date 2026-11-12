<?php

use App\Models\PatientMedicine;
use App\Models\PharmacyFollowup;
use App\Models\User;
use App\Notifications\CareAlertNotification;
use App\Services\RefillService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/** A medicine whose supply ends $days from today (negative = already ran out). */
function supplied(User $patient, int $days, array $attrs = []): PatientMedicine
{
    return PatientMedicine::factory()->for($patient, 'patient')->create($attrs + [
        'name' => 'Metformin',
        'start_date' => '2026-01-01',
        'supply_until' => CarbonImmutable::now()->addDays($days)->toDateString(),
    ]);
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00:00'));
    $this->pharmacyUser = User::factory()->pharmacy()->create();
    $this->pharmacy = $this->pharmacyUser->pharmacy;
    $this->patient = User::factory()->patient()->create(['name' => 'Lola Maria Cruz', 'phone' => '09171234567', 'email' => 'lola@example.com']);
    $this->patient->consentedPharmacies()->attach($this->pharmacy->id);
    $this->refills = app(RefillService::class);
});

it('lists medicines running out soon or already out, soonest first, with a status', function () {
    $soon = supplied($this->patient, 3);
    $overdue = supplied($this->patient, -5, ['name' => 'Losartan']);
    $stopped = supplied($this->patient, -20, ['name' => 'Amlodipine']);

    $rows = $this->refills->dueFor($this->pharmacy);

    expect($rows->pluck('medicine.id')->all())->toBe([$stopped->id, $overdue->id, $soon->id])
        ->and($rows->pluck('status')->all())->toBe(['stopped', 'overdue', 'due_soon'])
        ->and($rows->pluck('days_left')->all())->toBe([-20, -5, 3]);
});

it('leaves out anything that does not need a refill', function () {
    supplied($this->patient, 30);                                          // plenty left
    supplied($this->patient, 3, ['active' => false]);                      // stopped medicine
    supplied($this->patient, 3, ['end_date' => '2026-11-13']);             // supply outlasts the course
    PatientMedicine::factory()->for($this->patient, 'patient')->create();  // no supply date
    $needs = supplied($this->patient, 3, ['end_date' => '2026-12-31']);    // course continues past supply

    expect($this->refills->dueFor($this->pharmacy)->pluck('medicine.id')->all())->toBe([$needs->id]);
});

it('only includes patients who agreed to share with this pharmacy', function () {
    $stranger = User::factory()->patient()->create();
    supplied($stranger, 2);
    $otherPharmacy = User::factory()->pharmacy()->create();
    $this->patient->consentedPharmacies()->detach($this->pharmacy->id);
    $this->patient->consentedPharmacies()->attach($otherPharmacy->pharmacy->id);
    supplied($this->patient, 2);

    expect($this->refills->dueFor($this->pharmacy))->toBeEmpty()
        ->and($this->refills->dueFor($otherPharmacy->pharmacy))->toHaveCount(1);
});

it('shows the pharmacy only the patient name, medicine name and date', function () {
    supplied($this->patient, 3, ['dosage' => '777 mg secret dose']);

    $page = $this->actingAs($this->pharmacyUser)->get(route('pharmacy.refills'));

    $page->assertOk()->assertSee('Lola Maria Cruz')->assertSee('Metformin')->assertSee('Nov 15, 2026')->assertSee('Due soon')
        ->assertDontSee('777 mg')->assertDontSee('09171234567')->assertDontSee('lola@example.com');
});

it('pre-fills a friendly follow-up message', function () {
    $medicine = supplied($this->patient, 3);

    expect($this->refills->defaultMessage($this->pharmacy, $medicine))
        ->toContain('Hello Lola')->toContain('Metformin')->toContain($this->pharmacy->name);
});

it('sends a follow-up to the patient in the app and by email, and records it', function () {
    Notification::fake();
    $medicine = supplied($this->patient, 3);

    $this->actingAs($this->pharmacyUser)->post(route('pharmacy.refills.followup', $medicine), ['message' => 'Your refill is ready whenever you are.'])
        ->assertRedirect()->assertSessionHas('status');

    $followup = PharmacyFollowup::sole();
    expect($followup)->pharmacy_id->toBe($this->pharmacy->id)->patient_id->toBe($this->patient->id)->message->toBe('Your refill is ready whenever you are.');
    Notification::assertSentTo($this->patient, CareAlertNotification::class, fn ($n) => $n->message === 'Your refill is ready whenever you are.'
        && str_contains($n->subject, $this->pharmacy->name) && $n->channels === ['database', 'mail']);

    $this->actingAs($this->pharmacyUser)->get(route('pharmacy.refills'))->assertSee('last messaged');
});

it('does not let a pharmacy message the same patient about the same medicine twice in a day', function () {
    Notification::fake();
    $medicine = supplied($this->patient, 3);
    $send = fn () => $this->actingAs($this->pharmacyUser)->post(route('pharmacy.refills.followup', $medicine), ['message' => 'Hello there']);

    $send()->assertSessionHasNoErrors();
    $send()->assertSessionHasErrors('message');
    expect(PharmacyFollowup::count())->toBe(1);

    $this->travelTo(CarbonImmutable::now()->addHours(25));
    $send()->assertSessionHasNoErrors();
    expect(PharmacyFollowup::count())->toBe(2);
});

it('validates the message', function () {
    $medicine = supplied($this->patient, 3);

    $this->actingAs($this->pharmacyUser)->post(route('pharmacy.refills.followup', $medicine), ['message' => ''])->assertSessionHasErrors('message');
    $this->actingAs($this->pharmacyUser)->post(route('pharmacy.refills.followup', $medicine), ['message' => str_repeat('a', 501)])->assertSessionHasErrors('message');
    expect(PharmacyFollowup::count())->toBe(0);
});

it('blocks follow-ups to patients who did not consent and to medicines not due', function () {
    Notification::fake();
    $stranger = User::factory()->patient()->create();
    $unshared = supplied($stranger, 3);
    $notDue = supplied($this->patient, 60);

    $this->actingAs($this->pharmacyUser)->post(route('pharmacy.refills.followup', $unshared), ['message' => 'Hi'])->assertForbidden();
    $this->actingAs($this->pharmacyUser)->post(route('pharmacy.refills.followup', $notDue), ['message' => 'Hi'])->assertNotFound();

    expect(PharmacyFollowup::count())->toBe(0);
    Notification::assertNothingSent();
});

it('blocks everyone but pharmacies from the refill list', function () {
    $medicine = supplied($this->patient, 3);
    $doctor = User::factory()->doctor()->create();

    $this->actingAs($this->patient)->get(route('pharmacy.refills'))->assertForbidden();
    $this->actingAs($doctor)->post(route('pharmacy.refills.followup', $medicine), ['message' => 'Hi'])->assertForbidden();
});

it('shows the pharmacy overview counts', function () {
    supplied($this->patient, 3);
    supplied($this->patient, -30, ['name' => 'Losartan']);

    $this->actingAs($this->pharmacyUser)->get(route('pharmacy.dashboard'))
        ->assertOk()->assertSee('Refills due')->assertSee('May have stopped refilling')
        ->assertViewHas('due', fn ($due) => $due->count() === 2)->assertViewHas('stopped', 1)->assertViewHas('patients', 1);
});

it('stops sharing when the patient takes consent back', function () {
    supplied($this->patient, 3);
    $this->actingAs($this->patient)->delete(route('patient.pharmacies.revoke', $this->pharmacy));

    expect($this->refills->dueFor($this->pharmacy))->toBeEmpty();
});

it('saves and validates the supply date on a medicine', function () {
    $payload = ['name' => 'Metformin', 'dosage' => '500 mg', 'start_date' => '2026-11-01', 'active' => '1'];

    $this->actingAs($this->patient)->post(route('patient.medicines.store'), $payload + ['supply_until' => '2026-12-01'])->assertSessionHasNoErrors();
    expect($this->patient->medicines()->latest('id')->first()->supply_until->toDateString())->toBe('2026-12-01');

    $this->actingAs($this->patient)->post(route('patient.medicines.store'), $payload + ['supply_until' => '2026-10-01'])->assertSessionHasErrors('supply_until');
});
