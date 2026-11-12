<?php

use App\Models\Alert;
use App\Models\RiskAssessment;
use App\Models\User;
use App\Notifications\CareAlertNotification;

function assessed(User $patient, string $level, float $score = 50.0, bool $enough = true): RiskAssessment
{
    return RiskAssessment::create([
        'patient_id' => $patient->id,
        'assessed_on' => today()->toDateString(),
        'score' => $score,
        'level' => $level,
        'computed_at' => now(),
        'factors' => [
            'score' => $score, 'level' => $level, 'sufficient_data' => $enough, 'decided_doses' => 14, 'problem_slots' => [],
            'factors' => [['key' => 'adherence', 'label' => 'Doses taken (last 14 days)', 'points' => 20.0, 'max' => 40, 'detail' => 'Took 7 of 14 doses (50%).']],
        ],
    ]);
}

function linkedPatient(User $doctor, string $name): User
{
    $patient = User::factory()->patient()->create(['name' => $name]);
    $patient->doctors()->attach($doctor->id, ['status' => 'active']);

    return $patient;
}

beforeEach(function () {
    $this->doctor = User::factory()->doctor()->create();
});

it('lists patients with the highest risk and most alerts first', function () {
    $calm = linkedPatient($this->doctor, 'Calm Carla');
    $high = linkedPatient($this->doctor, 'High Harry');
    $medium = linkedPatient($this->doctor, 'Medium Mia');
    $none = linkedPatient($this->doctor, 'Unscored Uma');
    assessed($calm, 'low', 10);
    assessed($high, 'high', 80);
    assessed($medium, 'medium', 40);
    Alert::create(['patient_id' => $high->id, 'type' => 'emergency', 'level' => 'high', 'message' => 'x']);

    $page = $this->actingAs($this->doctor)->get(route('doctor.dashboard'));

    $page->assertOk()->assertSeeInOrder(['High Harry', 'Medium Mia', 'Calm Carla', 'Unscored Uma'])
        ->assertSee('High risk')->assertSee('1 open alert')->assertSee('Not enough data yet');
});

it('shows a patient\'s risk score with the reasons behind it', function () {
    $patient = linkedPatient($this->doctor, 'High Harry');
    assessed($patient, 'high', 72.5);

    $this->actingAs($this->doctor)->get(route('doctor.patients.show', $patient))
        ->assertOk()->assertSee('Adherence risk')->assertSee('High risk')->assertSee('72.5')
        ->assertSee('Took 7 of 14 doses (50%).')->assertSee('not a diagnosis');
});

it('says so when there is not enough data to score', function () {
    $patient = linkedPatient($this->doctor, 'New Nina');

    $this->actingAs($this->doctor)->get(route('doctor.patients.show', $patient))
        ->assertOk()->assertSee('Not enough intake data yet');
});

it('lets a linked doctor acknowledge and then resolve an alert', function () {
    $patient = linkedPatient($this->doctor, 'High Harry');
    $alert = Alert::create(['patient_id' => $patient->id, 'type' => 'emergency', 'level' => 'high', 'message' => 'High Harry has missed 3 doses in a row.']);

    $this->actingAs($this->doctor)->get(route('doctor.patients.show', $patient))
        ->assertSee('High Harry has missed 3 doses in a row.')->assertSee('Acknowledge')->assertSee('Mark resolved');

    $this->actingAs($this->doctor)->patch(route('doctor.alerts.acknowledge', $alert))->assertRedirect();
    expect($alert->fresh())->acknowledged_by->toBe($this->doctor->id)->and($alert->fresh()->isOpen())->toBeTrue();

    $this->actingAs($this->doctor)->patch(route('doctor.alerts.resolve', $alert))->assertRedirect();
    expect($alert->fresh())->resolution->toBe('doctor')->and($alert->fresh()->isOpen())->toBeFalse();

    $this->actingAs($this->doctor)->get(route('doctor.patients.show', $patient))->assertSee('resolved by doctor')->assertDontSee('Mark resolved');
});

it('resolving straight away also records who saw it', function () {
    $patient = linkedPatient($this->doctor, 'High Harry');
    $alert = Alert::create(['patient_id' => $patient->id, 'type' => 'risk', 'level' => 'high', 'message' => 'm']);

    $this->actingAs($this->doctor)->patch(route('doctor.alerts.resolve', $alert));

    expect($alert->fresh())->acknowledged_by->toBe($this->doctor->id)->and($alert->fresh()->acknowledged_at)->not->toBeNull();
});

it('keeps alerts away from unlinked doctors and patients', function () {
    $patient = linkedPatient($this->doctor, 'High Harry');
    $alert = Alert::create(['patient_id' => $patient->id, 'type' => 'emergency', 'level' => 'high', 'message' => 'm']);
    $stranger = User::factory()->doctor()->create();
    $pending = User::factory()->doctor()->create();
    $patient->doctors()->attach($pending->id, ['status' => 'pending']);

    foreach ([$stranger, $pending, $patient] as $user) {
        $this->actingAs($user)->patch(route('doctor.alerts.acknowledge', $alert))->assertForbidden();
        $this->actingAs($user)->patch(route('doctor.alerts.resolve', $alert))->assertForbidden();
    }

    expect($alert->fresh()->isOpen())->toBeTrue()->and($alert->fresh()->acknowledged_at)->toBeNull();
});

it('gives the doctor an alerts inbox with an unread badge', function () {
    $patient = linkedPatient($this->doctor, 'High Harry');
    $this->doctor->notify(new CareAlertNotification('Emergency alert', 'High Harry has missed 3 doses in a row.', route('doctor.patients.show', $patient)));

    $this->actingAs($this->doctor)->get(route('doctor.dashboard'))->assertSee('Alerts, 1 unread');

    $this->actingAs($this->doctor)->get(route('doctor.notifications'))
        ->assertOk()->assertSee('High Harry has missed 3 doses in a row.')->assertSee('Open')
        ->assertSee(route('doctor.patients.show', $patient));

    $this->actingAs($this->doctor)->post(route('doctor.notifications.read'))->assertRedirect();
    expect($this->doctor->unreadNotifications()->count())->toBe(0);
});

it('keeps the doctor alerts inbox for doctors', function () {
    $patient = User::factory()->patient()->create();

    $this->actingAs($patient)->get(route('doctor.notifications'))->assertForbidden();
});
