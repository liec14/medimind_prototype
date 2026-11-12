<?php

use App\Models\DoseLog;
use App\Models\PatientMedicine;
use App\Models\PatientRoutine;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleService;
use Carbon\CarbonImmutable;

function scheduled(array $medicine = [], array $schedule = []): PatientMedicine
{
    $med = PatientMedicine::factory()->create($medicine + ['start_date' => '2026-01-01']);
    Schedule::factory()->create($schedule + ['patient_medicine_id' => $med->id]);

    return $med->fresh();
}

beforeEach(function () {
    // Thursday 12 Nov 2026, 06:00, before any dose time used below.
    $this->travelTo(CarbonImmutable::parse('2026-11-12 06:00:00'));
    $this->service = app(ScheduleService::class);
});

it('suggests times from the routine for each preset', function () {
    $routine = new PatientRoutine(['wake_time' => '06:00', 'breakfast_time' => '07:00', 'lunch_time' => '12:30', 'dinner_time' => '19:00', 'sleep_time' => '21:30']);

    expect($this->service->suggestTimes('once_daily', $routine))->toBe(['07:00'])
        ->and($this->service->suggestTimes('twice_daily', $routine))->toBe(['07:00', '19:00'])
        ->and($this->service->suggestTimes('three_times_daily', $routine))->toBe(['07:00', '12:30', '19:00'])
        ->and($this->service->suggestTimes('four_times_daily', $routine))->toBe(['07:00', '12:30', '19:00', '21:30'])
        ->and($this->service->suggestTimes('custom', $routine))->toBe([])
        ->and($this->service->suggestTimes('twice_daily'))->toBe(['08:00', '18:30']);
});

it('generates a week of doses and is idempotent', function () {
    $med = scheduled();

    expect($this->service->generateDoseLogs($med))->toBe(14)
        ->and($this->service->generateDoseLogs($med))->toBe(0)
        ->and(DoseLog::count())->toBe(14)
        ->and(DoseLog::first()->patient_id)->toBe($med->patient_id)
        ->and(DoseLog::where('status', DoseLog::PENDING)->count())->toBe(14);
});

it('never creates doses in the past', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 15:00:00'));
    $med = scheduled(schedule: ['frequency' => 'once_daily', 'times' => ['08:00']]);

    $this->service->generateDoseLogs($med);

    expect(DoseLog::count())->toBe(6) // today's 08:00 has passed; 13th to 18th remain
        ->and(DoseLog::orderBy('scheduled_at')->first()->scheduled_at->toDateString())->toBe('2026-11-13');
});

it('only creates doses on the selected days of the week', function () {
    // Mon (1) and Wed (3); 12 Nov 2026 is a Thursday, so 7 days include Mon 16 and Wed 18.
    $med = scheduled(schedule: ['frequency' => 'once_daily', 'times' => ['08:00'], 'days_of_week' => [1, 3]]);

    $this->service->generateDoseLogs($med);

    expect(DoseLog::orderBy('scheduled_at')->pluck('scheduled_at')->map->toDateString()->all())
        ->toBe(['2026-11-16', '2026-11-18']);
});

it('respects the medicine start and end dates', function () {
    $med = scheduled(['start_date' => '2026-11-14', 'end_date' => '2026-11-15'], ['frequency' => 'once_daily', 'times' => ['08:00']]);

    $this->service->generateDoseLogs($med);

    expect(DoseLog::orderBy('scheduled_at')->pluck('scheduled_at')->map->toDateString()->all())
        ->toBe(['2026-11-14', '2026-11-15']);
});

it('generates nothing for inactive medicines or medicines without a schedule', function () {
    $inactive = scheduled(['active' => false]);
    $none = PatientMedicine::factory()->create();

    expect($this->service->generateDoseLogs($inactive))->toBe(0)
        ->and($this->service->generateDoseLogs($none))->toBe(0);
});

it('rebuilds upcoming doses when the schedule changes but keeps history', function () {
    $med = scheduled();
    $this->service->generateDoseLogs($med);
    $past = DoseLog::factory()->taken()->create(['patient_medicine_id' => $med->id, 'scheduled_at' => now()->subDay()]);
    $missed = DoseLog::factory()->missed()->create(['patient_medicine_id' => $med->id, 'scheduled_at' => now()->subDays(2)]);

    $this->service->saveSchedule($med, 'once_daily', ['09:00'], null);

    $pending = DoseLog::where('status', DoseLog::PENDING)->get();
    expect($pending)->toHaveCount(7)
        ->and($pending->every(fn ($d) => $d->scheduled_at->format('H:i') === '09:00'))->toBeTrue()
        ->and($past->fresh())->not->toBeNull()
        ->and($missed->fresh())->not->toBeNull();
});

it('treats all seven days as no restriction and sorts and dedupes times', function () {
    $med = PatientMedicine::factory()->create();

    $schedule = $this->service->saveSchedule($med, 'custom', ['20:00', '08:00', '08:00', ''], [0, 1, 2, 3, 4, 5, 6]);

    expect($schedule->days_of_week)->toBeNull()->and($schedule->times)->toBe(['08:00', '20:00']);
});

it('removes upcoming doses when the schedule is removed', function () {
    $med = scheduled();
    $this->service->generateDoseLogs($med);

    $this->service->removeSchedule($med);

    expect(DoseLog::count())->toBe(0)->and($med->fresh()->schedule)->toBeNull();
});

it('tops up every active scheduled medicine via the command', function () {
    scheduled();
    scheduled(['active' => false]);
    PatientMedicine::factory()->create(); // no schedule

    $this->artisan('doses:generate')->expectsOutputToContain('Created 14 dose records')->assertSuccessful();
    $this->artisan('doses:generate')->expectsOutputToContain('Created 0 dose records')->assertSuccessful();
});

it('summarizes a schedule in plain language', function () {
    $schedule = Schedule::factory()->make(['frequency' => 'twice_daily', 'times' => ['08:00', '20:00'], 'days_of_week' => [1, 3]]);

    expect($schedule->summary())->toBe('Twice a day · 8:00 AM, 8:00 PM · Mon, Wed');
});

it('stops future doses when a medicine is marked inactive', function () {
    $patient = User::factory()->patient()->create();
    $med = PatientMedicine::factory()->for($patient, 'patient')->create(['start_date' => '2026-01-01']);
    Schedule::factory()->create(['patient_medicine_id' => $med->id]);
    $this->service->generateDoseLogs($med);
    expect(DoseLog::count())->toBe(14);

    $this->actingAs($patient)->put(route('patient.medicines.update', $med), [
        'name' => $med->name, 'dosage' => $med->dosage, 'start_date' => '2026-01-01', 'active' => '0',
    ]);

    expect(DoseLog::count())->toBe(0);
});
