<?php

use App\Models\DoseLog;
use App\Models\PatientMedicine;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 06:00:00'));
    $this->patient = User::factory()->patient()->create();
    $this->medicine = PatientMedicine::factory()->for($this->patient, 'patient')->create(['start_date' => '2026-01-01']);
});

it('lets a patient save a schedule and creates upcoming doses', function () {
    $this->actingAs($this->patient)->put(route('patient.schedule.update', $this->medicine), [
        'frequency' => 'twice_daily', 'times' => ['08:00', '20:00', '', '', '', ''],
    ])->assertRedirect(route('patient.medicines.index'));

    expect($this->medicine->fresh()->schedule->times)->toBe(['08:00', '20:00'])
        ->and(DoseLog::count())->toBe(14);
});

it('rejects a time count that does not match the frequency', function () {
    $this->actingAs($this->patient)->put(route('patient.schedule.update', $this->medicine), [
        'frequency' => 'three_times_daily', 'times' => ['08:00', '20:00'],
    ])->assertSessionHasErrors('times');

    $this->actingAs($this->patient)->put(route('patient.schedule.update', $this->medicine), [
        'frequency' => 'once_daily', 'times' => ['', ''],
    ])->assertSessionHasErrors('times');

    $this->actingAs($this->patient)->put(route('patient.schedule.update', $this->medicine), [
        'frequency' => 'once_daily', 'times' => ['25:99'],
    ])->assertSessionHasErrors('times.0');

    $this->actingAs($this->patient)->put(route('patient.schedule.update', $this->medicine), [
        'frequency' => 'weekly', 'times' => ['08:00'],
    ])->assertSessionHasErrors('frequency');

    expect(Schedule::count())->toBe(0);
});

it('pre-fills times from the routine when a frequency is picked', function () {
    $this->patient->routine()->create(['breakfast_time' => '06:45', 'dinner_time' => '19:15']);

    $this->actingAs($this->patient)
        ->get(route('patient.schedule.edit', $this->medicine).'?frequency=twice_daily')
        ->assertOk()->assertSee('value="06:45"', false)->assertSee('value="19:15"', false);
});

it('shows saved times when editing an existing schedule', function () {
    Schedule::factory()->create(['patient_medicine_id' => $this->medicine->id, 'times' => ['09:30', '21:00']]);

    $this->actingAs($this->patient)->get(route('patient.schedule.edit', $this->medicine))
        ->assertOk()->assertSee('value="09:30"', false);
});

it('lets a patient remove a schedule', function () {
    Schedule::factory()->create(['patient_medicine_id' => $this->medicine->id]);

    $this->actingAs($this->patient)->delete(route('patient.schedule.destroy', $this->medicine))
        ->assertRedirect(route('patient.medicines.index'));

    expect(Schedule::count())->toBe(0);
});

it('saves the daily routine and validates it', function () {
    $this->actingAs($this->patient)->put(route('patient.routine.update'), [
        'wake_time' => '06:00', 'breakfast_time' => '07:00', 'lunch_time' => '12:00', 'dinner_time' => '19:00', 'sleep_time' => '21:00',
    ])->assertRedirect();
    expect($this->patient->fresh()->routine->dinner_time)->toBe('19:00');

    $this->actingAs($this->patient)->put(route('patient.routine.update'), ['wake_time' => 'late'])->assertSessionHasErrors('wake_time');
    $this->actingAs($this->patient)->get(route('patient.routine.edit'))->assertOk()->assertSee('value="19:00"', false);
});

it('blocks other patients and unlinked doctors from a schedule', function () {
    $payload = ['frequency' => 'once_daily', 'times' => ['08:00']];
    $other = User::factory()->patient()->create();
    $stranger = User::factory()->doctor()->create();

    $this->actingAs($other)->get(route('patient.schedule.edit', $this->medicine))->assertForbidden();
    $this->actingAs($other)->put(route('patient.schedule.update', $this->medicine), $payload)->assertForbidden();
    $this->actingAs($stranger)->get(route('doctor.schedule.edit', [$this->patient, $this->medicine]))->assertForbidden();
    $this->actingAs($stranger)->put(route('doctor.schedule.update', [$this->patient, $this->medicine]), $payload)->assertForbidden();
    expect(Schedule::count())->toBe(0);
});

it('lets a linked doctor correct the schedule', function () {
    $doctor = User::factory()->doctor()->create();
    $this->patient->doctors()->attach($doctor->id, ['status' => 'active']);

    $this->actingAs($doctor)->get(route('doctor.schedule.edit', [$this->patient, $this->medicine]))->assertOk();
    $this->actingAs($doctor)->put(route('doctor.schedule.update', [$this->patient, $this->medicine]), [
        'frequency' => 'once_daily', 'times' => ['07:30'],
    ])->assertRedirect(route('doctor.patients.show', $this->patient));

    expect($this->medicine->fresh()->schedule->times)->toBe(['07:30']);
    $this->actingAs($doctor)->get(route('doctor.patients.show', $this->patient))->assertSee('7:30 AM');
});

it('lists today\'s doses on the patient dashboard', function () {
    DoseLog::factory()->create(['patient_medicine_id' => $this->medicine->id, 'scheduled_at' => now()->setTime(8, 0)]);
    DoseLog::factory()->create(['patient_medicine_id' => $this->medicine->id, 'scheduled_at' => now()->addDay()->setTime(8, 0)]);

    $this->actingAs($this->patient)->get(route('patient.dashboard'))
        ->assertOk()->assertSee('8:00 AM')->assertSee($this->medicine->name)->assertSee('I took it');
});

it('shows the schedule summary on the medicine list', function () {
    Schedule::factory()->create(['patient_medicine_id' => $this->medicine->id]);

    $this->actingAs($this->patient)->get(route('patient.medicines.index'))
        ->assertOk()->assertSee('Twice a day')->assertSee('Schedule');
});
