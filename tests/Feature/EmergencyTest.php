<?php

use App\Models\Alert;
use App\Models\DoseLog;
use App\Models\PatientMedicine;
use App\Models\RiskAssessment;
use App\Models\TrustedContact;
use App\Models\User;
use App\Notifications\CareAlertNotification;
use App\Services\DoseService;
use App\Services\EmergencyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/** Decided doses, oldest first, one per hour ending a few hours ago. */
function streak(PatientMedicine $medicine, array $statuses): void
{
    $start = CarbonImmutable::now()->subHours(count($statuses) + 2);

    foreach ($statuses as $i => $status) {
        DoseLog::factory()->create([
            'patient_medicine_id' => $medicine->id,
            'scheduled_at' => $start->addHours($i),
            'status' => $status,
            'confirmed_at' => $status === 'taken' ? $start->addHours($i) : null,
        ]);
    }
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00'));
    $this->patient = User::factory()->patient()->create(['name' => 'Lola Maria']);
    $this->medicine = PatientMedicine::factory()->for($this->patient, 'patient')->create(['name' => 'Secretium', 'start_date' => '2026-01-01']);
    $this->emergency = app(EmergencyService::class);
});

it('counts missed doses in a row from the most recent', function () {
    streak($this->medicine, ['taken', 'missed', 'taken', 'missed', 'missed']);

    expect($this->emergency->consecutiveMisses($this->patient))->toBe(2);
});

it('does nothing below the threshold', function () {
    Notification::fake();
    streak($this->medicine, ['taken', 'missed', 'missed']);

    expect($this->emergency->evaluate($this->patient))->toBeNull();
    expect(Alert::count())->toBe(0);
    Notification::assertNothingSent();
});

it('alerts contacts and doctors when the threshold is reached', function () {
    Notification::fake();
    $doctor = User::factory()->doctor()->create();
    $pendingDoctor = User::factory()->doctor()->create();
    $this->patient->doctors()->attach($doctor->id, ['status' => 'active']);
    $this->patient->doctors()->attach($pendingDoctor->id, ['status' => 'pending']);
    TrustedContact::factory()->create(['patient_id' => $this->patient->id, 'name' => 'Ana', 'email' => 'ana@example.com']);
    TrustedContact::factory()->create(['patient_id' => $this->patient->id, 'email' => 'muted@example.com', 'notify' => false]);
    streak($this->medicine, ['taken', 'missed', 'missed', 'missed']);

    $alert = $this->emergency->evaluate($this->patient);

    expect($alert)->type->toBe('emergency')->level->toBe('high')
        ->and($alert->message)->toBe('Lola Maria has missed 3 doses in a row.')
        ->and($alert->data)->toMatchArray(['misses' => 3, 'contacts' => 1, 'doctors' => 1]);

    Notification::assertSentOnDemand(CareAlertNotification::class, function ($n, $channels, $notifiable) {
        return array_key_exists('ana@example.com', $notifiable->routes['mail']) && $channels === ['mail'];
    });
    Notification::assertSentOnDemandTimes(CareAlertNotification::class, 1); // the muted contact is not emailed
    Notification::assertSentTo($doctor, CareAlertNotification::class, fn ($n) => str_contains($n->url, '/doctor/patients/'.$this->patient->id));
    Notification::assertNotSentTo($pendingDoctor, CareAlertNotification::class);
    Notification::assertSentTo($this->patient, CareAlertNotification::class, fn ($n) => $n->channels === ['database'] && str_contains($n->message, '1 trusted contact and 1 doctor'));
});

it('keeps health details out of the email to a trusted contact', function () {
    Notification::fake();
    TrustedContact::factory()->create(['patient_id' => $this->patient->id, 'email' => 'ana@example.com']);
    streak($this->medicine, ['missed', 'missed', 'missed']);

    $this->emergency->evaluate($this->patient);

    Notification::assertSentOnDemand(CareAlertNotification::class, function ($n) {
        return str_contains($n->message, 'Lola Maria has missed 3 medicine doses in a row')
            && ! str_contains($n->message, 'Secretium')
            && $n->url === null;
    });
});

it('tells the patient when there is nobody to alert', function () {
    Notification::fake();
    streak($this->medicine, ['missed', 'missed', 'missed']);

    $this->emergency->evaluate($this->patient);

    Notification::assertSentTo($this->patient, CareAlertNotification::class, fn ($n) => str_contains($n->message, 'no one'));
});

it('raises only one alert per streak', function () {
    Notification::fake();
    TrustedContact::factory()->create(['patient_id' => $this->patient->id]);
    streak($this->medicine, ['missed', 'missed', 'missed']);

    $this->emergency->evaluate($this->patient);
    expect($this->emergency->evaluate($this->patient))->toBeNull();

    expect(Alert::count())->toBe(1);
    Notification::assertSentOnDemandTimes(CareAlertNotification::class, 1);
});

it('closes the alert when the patient takes a dose, and a new streak raises a new one', function () {
    Notification::fake();
    streak($this->medicine, ['missed', 'missed', 'missed']);
    $this->emergency->evaluate($this->patient);

    $due = DoseLog::factory()->create(['patient_medicine_id' => $this->medicine->id, 'scheduled_at' => now()->subMinutes(5)]);
    app(DoseService::class)->confirm($due);

    expect(Alert::sole())->resolution->toBe('resumed')->and(Alert::sole()->isOpen())->toBeFalse();
    expect($this->emergency->consecutiveMisses($this->patient))->toBe(0);

    // Three more misses after that dose.
    foreach ([1, 2, 3] as $minutesAhead) {
        DoseLog::factory()->missed()->create(['patient_medicine_id' => $this->medicine->id, 'scheduled_at' => now()->addMinutes($minutesAhead)]);
    }
    expect($this->emergency->evaluate($this->patient))->not->toBeNull()->and(Alert::count())->toBe(2);
});

it('runs the emergency rule and re-scores risk when doses are marked missed', function () {
    Notification::fake();
    $doctor = User::factory()->doctor()->create();
    $this->patient->doctors()->attach($doctor->id, ['status' => 'active']);

    foreach ([5, 4, 3] as $hoursAgo) {
        DoseLog::factory()->create(['patient_medicine_id' => $this->medicine->id, 'scheduled_at' => now()->subHours($hoursAgo)]);
    }

    expect(app(DoseService::class)->markMissed())->toBe(3);

    expect(Alert::where('type', 'emergency')->count())->toBe(1)
        ->and(RiskAssessment::where('patient_id', $this->patient->id)->count())->toBe(1);
    Notification::assertSentTo($doctor, CareAlertNotification::class);
});

it('stores the doctor alert in the in-app inbox with a link to the patient', function () {
    $doctor = User::factory()->doctor()->create();
    $this->patient->doctors()->attach($doctor->id, ['status' => 'active']);
    streak($this->medicine, ['missed', 'missed', 'missed']);

    $this->emergency->evaluate($this->patient);

    $data = $doctor->notifications()->first()->data;
    expect($data['message'])->toContain('Lola Maria has missed 3 doses in a row')
        ->and($data['url'])->toBe(route('doctor.patients.show', $this->patient));
});
