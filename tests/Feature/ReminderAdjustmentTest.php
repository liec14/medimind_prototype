<?php

use App\Models\DoseLog;
use App\Models\PatientMedicine;
use App\Models\ReminderSuggestion;
use App\Models\Schedule;
use App\Models\User;
use App\Services\AdherenceRiskService;
use App\Services\ReminderAdjustmentService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00'));
    $this->patient = User::factory()->patient()->create();
    $this->medicine = PatientMedicine::factory()->for($this->patient, 'patient')->create(['name' => 'Metformin', 'start_date' => '2026-01-01']);
    Schedule::factory()->create(['patient_medicine_id' => $this->medicine->id, 'times' => ['08:00', '20:00']]);
    $this->service = app(ReminderAdjustmentService::class);
});

function missEveningsTakeMornings(PatientMedicine $medicine): void
{
    foreach (range(1, 7) as $d) {
        foreach ([['08:00', 'taken'], ['20:00', 'missed']] as [$time, $status]) {
            DoseLog::factory()->create([
                'patient_medicine_id' => $medicine->id,
                'scheduled_at' => CarbonImmutable::now()->subDays($d)->setTimeFromTimeString($time),
                'status' => $status,
            ]);
        }
    }
}

it('suggests moving only the time of day that is often missed', function () {
    missEveningsTakeMornings($this->medicine);

    app(AdherenceRiskService::class)->assess($this->patient);

    $suggestion = ReminderSuggestion::sole();
    expect($suggestion)->slot->toBe('evening')->shift_minutes->toBe(30)->status->toBe('pending')
        ->and($suggestion->patient_medicine_id)->toBe($this->medicine->id);
});

it('does not suggest anything for a medicine with no dose in the problem time of day', function () {
    $this->medicine->schedule->update(['times' => ['08:00']]);

    $this->service->sync($this->patient, [['slot' => 'evening']]);

    expect(ReminderSuggestion::count())->toBe(0);
});

it('moves the reminder earlier when accepted and rebuilds upcoming doses', function () {
    missEveningsTakeMornings($this->medicine);
    app(AdherenceRiskService::class)->assess($this->patient);
    $suggestion = ReminderSuggestion::sole();

    $this->actingAs($this->patient)->post(route('patient.suggestions.accept', $suggestion))->assertRedirect();

    expect($this->medicine->fresh()->schedule->times)->toBe(['08:00', '19:30'])
        ->and($suggestion->fresh()->status)->toBe('accepted');

    $upcoming = DoseLog::where('status', 'pending')->orderBy('scheduled_at')->pluck('scheduled_at')->map->format('H:i')->unique()->sort()->values()->all();
    expect($upcoming)->toBe(['08:00', '19:30']);
});

it('stops at midnight instead of wrapping around', function () {
    $this->medicine->schedule->update(['times' => ['00:10']]);
    $suggestion = ReminderSuggestion::create(['patient_medicine_id' => $this->medicine->id, 'slot' => 'night', 'shift_minutes' => 30, 'status' => 'pending']);

    $this->service->accept($suggestion);

    expect($this->medicine->fresh()->schedule->times)->toBe(['00:00']);
});

it('leaves reminder times alone when dismissed, and does not nag for 30 days', function () {
    $this->service->sync($this->patient, [['slot' => 'evening']]);
    $suggestion = ReminderSuggestion::sole();

    $this->actingAs($this->patient)->post(route('patient.suggestions.dismiss', $suggestion))->assertRedirect();
    expect($suggestion->fresh()->status)->toBe('dismissed')->and($this->medicine->fresh()->schedule->times)->toBe(['08:00', '20:00']);

    $this->service->sync($this->patient, [['slot' => 'evening']]);
    expect($suggestion->fresh()->status)->toBe('dismissed');

    $this->travelTo(CarbonImmutable::now()->addDays(31));
    $this->service->sync($this->patient, [['slot' => 'evening']]);
    expect($suggestion->fresh()->status)->toBe('pending');
});

it('removes a pending suggestion once the time of day is no longer a problem', function () {
    $this->service->sync($this->patient, [['slot' => 'evening']]);
    expect(ReminderSuggestion::count())->toBe(1);

    $this->service->sync($this->patient, []);

    expect(ReminderSuggestion::count())->toBe(0);
});

it('shows the suggestion on the patient dashboard', function () {
    $this->service->sync($this->patient, [['slot' => 'evening']]);

    $this->actingAs($this->patient)->get(route('patient.dashboard'))
        ->assertOk()->assertSee('often miss your evening doses of Metformin')->assertSee('Yes, remind me earlier');
});

it('only lets the owner answer a suggestion, and only while it is pending', function () {
    $this->service->sync($this->patient, [['slot' => 'evening']]);
    $suggestion = ReminderSuggestion::sole();
    $other = User::factory()->patient()->create();

    $this->actingAs($other)->post(route('patient.suggestions.accept', $suggestion))->assertForbidden();
    $this->actingAs($other)->post(route('patient.suggestions.dismiss', $suggestion))->assertForbidden();
    expect($this->medicine->fresh()->schedule->times)->toBe(['08:00', '20:00']);

    $this->actingAs($this->patient)->post(route('patient.suggestions.dismiss', $suggestion));
    $this->actingAs($this->patient)->post(route('patient.suggestions.accept', $suggestion))->assertNotFound();
});
