<?php

use App\Models\Alert;
use App\Models\DoseLog;
use App\Models\PatientMedicine;
use App\Models\RiskAssessment;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\CareAlertNotification;
use App\Services\AdherenceRiskService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Add dose history. Each row: [days ago, 'HH:MM', 'taken'|'missed'].
 * Today in these tests is Thursday 12 Nov 2026, 12:00.
 */
function logRows(PatientMedicine $medicine, array $rows): void
{
    foreach ($rows as [$daysAgo, $time, $status]) {
        DoseLog::factory()->create([
            'patient_medicine_id' => $medicine->id,
            'scheduled_at' => CarbonImmutable::now()->subDays($daysAgo)->setTimeFromTimeString($time),
            'status' => $status,
        ]);
    }
}

/** 7 days: morning dose taken, evening dose missed. */
function eveningMisses(PatientMedicine $medicine, int $snoozes = 0): void
{
    foreach (range(1, 7) as $d) {
        foreach ([['08:00', 'taken'], ['20:00', 'missed']] as [$time, $status]) {
            DoseLog::factory()->create([
                'patient_medicine_id' => $medicine->id,
                'scheduled_at' => CarbonImmutable::now()->subDays($d)->setTimeFromTimeString($time),
                'status' => $status,
                'snoozed_count' => $snoozes,
            ]);
        }
    }
}

function factorOf(array $result, string $key): array
{
    return collect($result['factors'])->firstWhere('key', $key);
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00'));
    $this->patient = User::factory()->patient()->create();
    $this->medicine = PatientMedicine::factory()->for($this->patient, 'patient')->create(['start_date' => '2026-01-01']);
    $this->risk = app(AdherenceRiskService::class);
});

it('does not score a patient with too little data', function () {
    logRows($this->medicine, [[1, '08:00', 'taken'], [2, '08:00', 'missed'], [3, '08:00', 'missed']]);

    $result = $this->risk->compute($this->patient);

    expect($result)->toMatchArray(['score' => 0.0, 'level' => 'low', 'sufficient_data' => false, 'decided_doses' => 3])
        ->and($result['factors'])->toBeEmpty();
});

it('scores perfect adherence as low', function () {
    foreach (range(1, 14) as $d) {
        logRows($this->medicine, [[$d, '08:00', 'taken']]);
    }

    $result = $this->risk->compute($this->patient);

    expect($result['sufficient_data'])->toBeTrue()->and($result['score'])->toBe(0.0)->and($result['level'])->toBe('low');
});

it('flags a pattern of missed evening doses as medium risk and names the time of day', function () {
    eveningMisses($this->medicine);

    $result = $this->risk->compute($this->patient);

    expect($result['level'])->toBe('medium')
        ->and($result['score'])->toBeGreaterThan(38)->toBeLessThan(44)
        ->and($result['problem_slots'])->toHaveCount(1)
        ->and($result['problem_slots'][0])->toMatchArray(['slot' => 'evening', 'missed' => 7, 'total' => 7])
        ->and(factorOf($result, 'adherence')['points'])->toBe(20.0)
        ->and(factorOf($result, 'time_pattern')['detail'])->toBe('7 of 7 evening doses were missed.');
});

it('scores a long streak of missed doses as high risk with every factor explained', function () {
    foreach (range(14, 12) as $d) {
        logRows($this->medicine, [[$d, '08:00', 'taken']]);
    }
    foreach (range(11, 1) as $d) {
        logRows($this->medicine, [[$d, '08:00', 'missed']]);
    }

    $result = $this->risk->compute($this->patient);

    expect($result['level'])->toBe('high')->and($result['score'])->toBeGreaterThanOrEqual(60)
        ->and(collect($result['factors'])->pluck('key')->all())->toBe(['adherence', 'streak', 'time_pattern', 'weekday_pattern', 'complexity', 'snooze'])
        ->and(factorOf($result, 'streak'))->toMatchArray(['points' => 25.0, 'detail' => 'The last 11 doses were missed.'])
        ->and(collect($result['factors'])->every(fn ($f) => $f['points'] <= $f['max'] && $f['detail'] !== ''))->toBeTrue();
});

it('adds points when reminders are often snoozed', function () {
    eveningMisses($this->medicine);
    $before = $this->risk->compute($this->patient);

    DoseLog::query()->update(['snoozed_count' => 3]);
    $after = $this->risk->compute($this->patient);

    expect(factorOf($before, 'snooze')['points'])->toBe(0.0)
        ->and(factorOf($after, 'snooze')['points'])->toBe(10.0)
        ->and($after['score'])->toBe(round($before['score'] + 10, 1));
});

it('adds points for a regimen with many doses a day', function () {
    foreach (range(1, 14) as $d) {
        logRows($this->medicine, [[$d, '08:00', 'taken']]);
    }
    Schedule::factory()->create(['patient_medicine_id' => $this->medicine->id, 'frequency' => 'four_times_daily', 'times' => ['08:00', '12:00', '18:00', '22:00']]);

    $complexity = factorOf($this->risk->compute($this->patient), 'complexity');

    expect($complexity['points'])->toBe(5.0)->and($complexity['detail'])->toBe('4 doses a day across 1 medicine.');
});

it('reads its thresholds from config', function () {
    eveningMisses($this->medicine);
    expect($this->risk->compute($this->patient)['level'])->toBe('medium');

    config(['medimind.risk.high' => 10]);
    expect($this->risk->compute($this->patient)['level'])->toBe('high');

    config(['medimind.risk.min_doses' => 100]);
    expect($this->risk->compute($this->patient)['sufficient_data'])->toBeFalse();
});

it('buckets hours into time-of-day slots', function () {
    expect(collect([5, 10, 11, 16, 17, 20, 21, 23, 0, 4])->map(fn ($h) => AdherenceRiskService::slotFor($h))->all())
        ->toBe(['morning', 'morning', 'afternoon', 'afternoon', 'evening', 'evening', 'night', 'night', 'night', 'night']);
});

it('saves one assessment per day and keeps the history', function () {
    eveningMisses($this->medicine);

    $first = $this->risk->assess($this->patient);
    $again = $this->risk->assess($this->patient);

    expect(RiskAssessment::count())->toBe(1)->and($again->id)->toBe($first->id)
        ->and($first->level)->toBe('medium')->and($first->hasEnoughData())->toBeTrue();

    $this->travelTo(CarbonImmutable::parse('2026-11-13 12:00:00'));
    $next = $this->risk->assess($this->patient);

    expect(RiskAssessment::count())->toBe(2)->and($this->patient->fresh()->latestRisk->id)->toBe($next->id);
});

it('raises one high-risk alert, tells the linked doctors, and clears it when adherence recovers', function () {
    Notification::fake();
    $doctor = User::factory()->doctor()->create();
    $pendingDoctor = User::factory()->doctor()->create();
    $this->patient->doctors()->attach($doctor->id, ['status' => 'active']);
    $this->patient->doctors()->attach($pendingDoctor->id, ['status' => 'pending']);

    foreach (range(14, 12) as $d) {
        logRows($this->medicine, [[$d, '08:00', 'taken']]);
    }
    foreach (range(11, 1) as $d) {
        logRows($this->medicine, [[$d, '08:00', 'missed']]);
    }

    $this->risk->assess($this->patient);
    $this->risk->assess($this->patient);

    $alert = Alert::sole();
    expect($alert)->type->toBe('risk')->level->toBe('high')->and($alert->isOpen())->toBeTrue();
    Notification::assertSentToTimes($doctor, CareAlertNotification::class, 1);
    Notification::assertNotSentTo($pendingDoctor, CareAlertNotification::class);

    // Adherence recovers: bad history replaced by a good one.
    DoseLog::query()->delete();
    foreach (range(1, 14) as $d) {
        logRows($this->medicine, [[$d, '08:00', 'taken']]);
    }
    $this->risk->assess($this->patient);

    expect($alert->fresh())->resolution->toBe('improved')->and($alert->fresh()->isOpen())->toBeFalse();
});

it('assesses only patients with active medicines', function () {
    $other = User::factory()->patient()->create();
    PatientMedicine::factory()->for($other, 'patient')->inactive()->create();
    User::factory()->patient()->create(); // no medicines at all

    expect($this->risk->assessAll())->toBe(1);

    $this->artisan('risk:assess')->expectsOutputToContain('Assessed 1 patients')->assertSuccessful();
});

it('registers the nightly job on the scheduler', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('risk:assess')->assertSuccessful();
});
