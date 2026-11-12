<?php

use App\Models\DoseLog;
use App\Models\PatientMedicine;
use App\Models\User;
use App\Notifications\DoseReminder;
use App\Services\AdherenceService;
use App\Services\DoseService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function dose(string $at, array $attrs = []): DoseLog
{
    return DoseLog::factory()->create($attrs + ['scheduled_at' => CarbonImmutable::parse($at)]);
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 08:30:00'));
    $this->service = app(DoseService::class);
});

// --- confirm -----------------------------------------------------------------

it('confirms a due dose as taken', function () {
    $dose = dose('2026-11-12 08:00');

    $this->service->confirm($dose);

    expect($dose->fresh())->status->toBe('taken')->confirmed_at->toEqual(now());
});

it('allows confirming shortly before the scheduled time but not too early', function () {
    $soon = dose('2026-11-12 09:00');   // 30 min ahead: inside the 60 min early window
    $later = dose('2026-11-12 12:00');

    $this->service->confirm($soon);
    expect($soon->fresh()->status)->toBe('taken');

    expect(fn () => $this->service->confirm($later))->toThrow(ValidationException::class, 'too early');
    expect($later->fresh()->status)->toBe('pending');
});

it('refuses to change a dose that is already taken or missed', function () {
    $taken = dose('2026-11-12 08:00', ['status' => 'taken']);
    $missed = dose('2026-11-12 07:00', ['status' => 'missed']);

    expect(fn () => $this->service->confirm($taken))->toThrow(ValidationException::class, 'already recorded');
    expect(fn () => $this->service->confirm($missed))->toThrow(ValidationException::class, 'marked as missed');
    expect($missed->fresh()->status)->toBe('missed');
});

// --- snooze ------------------------------------------------------------------

it('snoozes a due dose and makes it eligible for a new reminder', function () {
    $dose = dose('2026-11-12 08:00', ['reminded_at' => now()->subMinutes(30)]);

    $this->service->snooze($dose);

    $dose = $dose->fresh();
    expect($dose->snoozed_count)->toBe(1)
        ->and($dose->snoozed_until->toDateTimeString())->toBe('2026-11-12 08:40:00')
        ->and($dose->reminded_at)->toBeNull();
});

it('limits snoozing', function () {
    $dose = dose('2026-11-12 08:00', ['snoozed_count' => 3]);

    expect(fn () => $this->service->snooze($dose))->toThrow(ValidationException::class, 'maximum');
});

it('does not snooze a dose that is not due yet or would be missed first', function () {
    $future = dose('2026-11-12 10:00');
    $nearlyMissed = dose('2026-11-12 07:35'); // grace ends 08:35, snooze would end 08:40

    expect(fn () => $this->service->snooze($future))->toThrow(ValidationException::class, 'not due');
    expect(fn () => $this->service->snooze($nearlyMissed))->toThrow(ValidationException::class, 'too late');
});

// --- missed ------------------------------------------------------------------

it('marks only pending doses past the grace window as missed', function () {
    $overdue = dose('2026-11-12 07:00');           // 90 min ago
    $atBoundary = dose('2026-11-12 07:30');        // exactly 60 min ago: still inside the window
    $recent = dose('2026-11-12 08:00');
    $future = dose('2026-11-12 20:00');
    $taken = dose('2026-11-12 06:00', ['status' => 'taken']);

    expect($this->service->markMissed())->toBe(1);

    expect($overdue->fresh()->status)->toBe('missed')
        ->and($atBoundary->fresh()->status)->toBe('pending')
        ->and($recent->fresh()->status)->toBe('pending')
        ->and($future->fresh()->status)->toBe('pending')
        ->and($taken->fresh()->status)->toBe('taken');
});

// --- reminders ---------------------------------------------------------------

it('sends a reminder for a due dose exactly once', function () {
    Notification::fake();
    $dose = dose('2026-11-12 08:00');

    expect($this->service->sendDueReminders())->toBe(1)
        ->and($this->service->sendDueReminders())->toBe(0);

    Notification::assertSentToTimes($dose->patient, DoseReminder::class, 1);
    expect($dose->fresh()->reminded_at)->not->toBeNull();
});

it('does not remind for future, over-grace, or already-handled doses', function () {
    Notification::fake();
    dose('2026-11-12 09:00');                            // future
    dose('2026-11-12 07:00');                            // past grace
    dose('2026-11-12 08:00', ['status' => 'taken']);
    dose('2026-11-12 08:10', ['status' => 'missed']);

    expect($this->service->sendDueReminders())->toBe(0);
    Notification::assertNothingSent();
});

it('reminds again after a snooze has elapsed, not before', function () {
    Notification::fake();
    $dose = dose('2026-11-12 08:00');
    $this->service->sendDueReminders();
    $this->service->snooze($dose->fresh());

    $this->travelTo(CarbonImmutable::parse('2026-11-12 08:35'));
    expect($this->service->sendDueReminders())->toBe(0);

    $this->travelTo(CarbonImmutable::parse('2026-11-12 08:41'));
    expect($this->service->sendDueReminders())->toBe(1);
    Notification::assertSentToTimes($dose->patient, DoseReminder::class, 2);
});

it('stores an in-app notification and an email, and skips it once the dose is taken', function () {
    $dose = dose('2026-11-12 08:00');
    $patient = $dose->patient;

    $this->service->sendDueReminders();

    expect($patient->notifications)->toHaveCount(1)
        ->and($patient->notifications->first()->data['medicine'])->toBe($dose->medicine->name);

    // A reminder that was queued but whose dose has since been confirmed is not sent.
    $other = dose('2026-11-12 08:05');
    $other->update(['status' => 'taken']);
    $notification = new DoseReminder($other);
    expect($notification->shouldSend($other->patient, 'mail'))->toBeFalse();
});

it('builds a readable reminder email', function () {
    $dose = dose('2026-11-12 08:00');
    $mail = (new DoseReminder($dose))->toMail($dose->patient);

    expect($mail->subject)->toBe('Time to take '.$dose->medicine->name)
        ->and(implode(' ', $mail->introLines))->toContain('8:00 AM');
});

it('runs the missed and reminder commands', function () {
    Notification::fake();
    dose('2026-11-12 07:00'); // becomes missed
    dose('2026-11-12 08:00'); // gets a reminder

    $this->artisan('doses:mark-missed')->expectsOutputToContain('Marked 1 doses')->assertSuccessful();
    $this->artisan('reminders:dispatch')->expectsOutputToContain('Sent 1 reminders')->assertSuccessful();
});

it('registers the per-minute jobs on the scheduler', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('doses:mark-missed')
        ->expectsOutputToContain('reminders:dispatch')
        ->assertSuccessful();
});

// --- adherence ---------------------------------------------------------------

it('computes adherence from taken and missed doses only', function () {
    $med = PatientMedicine::factory()->create();
    $patient = $med->patient;
    $hour = 0; // each dose needs its own time (unique per medicine)
    foreach ([['taken', 3], ['missed', 1], ['pending', 2]] as [$status, $n]) {
        for ($i = 0; $i < $n; $i++) {
            DoseLog::factory()->create(['patient_medicine_id' => $med->id, 'status' => $status, 'scheduled_at' => now()->subHours(++$hour)]);
        }
    }

    $summary = app(AdherenceService::class)->summary($patient, now()->subDay(), now());

    expect($summary)->toMatchArray(['taken' => 3, 'missed' => 1, 'pending' => 2, 'rate' => 75.0]);
});

it('has no rate when nothing has been decided yet', function () {
    $patient = User::factory()->patient()->create();

    expect(app(AdherenceService::class)->summary($patient, now()->subDay(), now())['rate'])->toBeNull();
});

it('buckets adherence by Monday-to-Sunday week', function () {
    $med = PatientMedicine::factory()->create();
    // 12 Nov 2026 is a Thursday; this week starts Mon 9 Nov, last week Mon 2 Nov.
    foreach ([['2026-11-10 08:00', 'taken'], ['2026-11-11 08:00', 'missed'], ['2026-11-03 08:00', 'taken'], ['2026-11-04 08:00', 'taken']] as [$at, $status]) {
        DoseLog::factory()->create(['patient_medicine_id' => $med->id, 'scheduled_at' => $at, 'status' => $status]);
    }

    $weeks = app(AdherenceService::class)->weekly($med->patient, 8);

    expect($weeks)->toHaveCount(8)
        ->and($weeks[7])->toMatchArray(['taken' => 1, 'missed' => 1, 'rate' => 50.0])
        ->and($weeks[6])->toMatchArray(['taken' => 2, 'missed' => 0, 'rate' => 100.0])
        ->and($weeks[0]['rate'])->toBeNull();
});
