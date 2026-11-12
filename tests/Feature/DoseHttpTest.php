<?php

use App\Models\DoseLog;
use App\Models\PatientMedicine;
use App\Models\User;
use App\Services\DoseService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 08:30:00'));
    $this->patient = User::factory()->patient()->create();
    $this->medicine = PatientMedicine::factory()->for($this->patient, 'patient')->create(['name' => 'Metformin']);
    $this->dose = DoseLog::factory()->create(['patient_medicine_id' => $this->medicine->id, 'scheduled_at' => '2026-11-12 08:00']);
});

it('lets a patient confirm a dose from the dashboard', function () {
    $this->actingAs($this->patient)->get(route('patient.dashboard'))
        ->assertOk()->assertSee('Due now')->assertSee('I took it')->assertSee('Remind me in 10 min');

    $this->actingAs($this->patient)->post(route('patient.doses.confirm', $this->dose))->assertRedirect();

    expect($this->dose->fresh()->status)->toBe('taken');
    $this->actingAs($this->patient)->get(route('patient.dashboard'))->assertSee('Taken at 8:30 AM');
});

it('lets a patient snooze and shows when the next reminder comes', function () {
    $this->actingAs($this->patient)->post(route('patient.doses.snooze', $this->dose))->assertRedirect();

    $this->actingAs($this->patient)->get(route('patient.dashboard'))->assertSee('Reminding you again at 8:40 AM');
});

it('shows an error when a dose cannot be confirmed', function () {
    $this->dose->update(['status' => 'missed']);

    $this->actingAs($this->patient)->post(route('patient.doses.confirm', $this->dose))
        ->assertSessionHasErrors('dose');
    expect($this->dose->fresh()->status)->toBe('missed');
});

it('stops other users from recording someone else\'s intake', function () {
    $other = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();
    $this->patient->doctors()->attach($doctor->id, ['status' => 'active']);

    $this->actingAs($other)->post(route('patient.doses.confirm', $this->dose))->assertForbidden();
    $this->actingAs($other)->post(route('patient.doses.snooze', $this->dose))->assertForbidden();
    $this->actingAs($doctor)->post(route('patient.doses.confirm', $this->dose))->assertForbidden();
    expect($this->dose->fresh()->status)->toBe('pending');
});

it('summarizes today and the last seven days on the dashboard', function () {
    DoseLog::factory()->taken()->create(['patient_medicine_id' => $this->medicine->id, 'scheduled_at' => '2026-11-12 06:00']);
    DoseLog::factory()->missed()->create(['patient_medicine_id' => $this->medicine->id, 'scheduled_at' => '2026-11-10 06:00']);

    $this->actingAs($this->patient)->get(route('patient.dashboard'))
        ->assertOk()->assertSee('Taken today')->assertSee('50%');
});

it('shows history with filters and the weekly chart', function () {
    $other = PatientMedicine::factory()->for($this->patient, 'patient')->create(['name' => 'Losartan']);
    DoseLog::factory()->taken()->create(['patient_medicine_id' => $this->medicine->id, 'scheduled_at' => '2026-11-11 08:00']);
    DoseLog::factory()->missed()->create(['patient_medicine_id' => $other->id, 'scheduled_at' => '2026-11-11 09:00']);
    DoseLog::factory()->missed()->create(['patient_medicine_id' => $other->id, 'scheduled_at' => '2026-08-01 09:00']); // outside 30 days

    $page = $this->actingAs($this->patient)->get(route('patient.history'));
    $page->assertOk()->assertSee('Doses taken, by week')->assertSee('Losartan')->assertSee('Metformin');
    expect($page->viewData('doses'))->toHaveCount(3); // pending 08:00 today + 2 within 30 days

    expect($this->actingAs($this->patient)->get(route('patient.history', ['status' => 'missed']))->viewData('doses'))->toHaveCount(1);
    expect($this->actingAs($this->patient)->get(route('patient.history', ['medicine_id' => $this->medicine->id]))->viewData('doses'))->toHaveCount(2);
    expect($this->actingAs($this->patient)->get(route('patient.history', ['range' => 'all', 'status' => 'missed']))->viewData('doses'))->toHaveCount(2);
    $this->actingAs($this->patient)->get(route('patient.history', ['status' => 'bogus']))->assertSessionHasErrors('status');
});

it('never shows another patient\'s history', function () {
    $stranger = User::factory()->patient()->create();

    expect($this->actingAs($stranger)->get(route('patient.history'))->viewData('doses'))->toHaveCount(0);
});

it('shows reminders, an unread badge, and can mark them read', function () {
    app(DoseService::class)->sendDueReminders();

    $this->actingAs($this->patient)->get(route('patient.dashboard'))->assertSee('Reminders, 1 unread');
    $this->actingAs($this->patient)->get(route('patient.notifications'))
        ->assertOk()->assertSee('Time to take Metformin');

    $this->actingAs($this->patient)->post(route('patient.notifications.read'))->assertRedirect();
    expect($this->patient->unreadNotifications()->count())->toBe(0);
});
