<?php

namespace App\Services;

use App\Models\DoseLog;
use App\Models\User;
use App\Notifications\DoseReminder;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class DoseService
{
    public function __construct(private AdherenceRiskService $risk, private EmergencyService $emergency) {}

    /** Patient confirms a dose was taken. Only pending doses, and not too far ahead. */
    public function confirm(DoseLog $dose): DoseLog
    {
        $now = CarbonImmutable::now();

        $this->ensurePending($dose);

        $earliest = $dose->scheduled_at->subMinutes((int) config('medimind.dose.early_minutes'));
        if ($earliest->gt($now)) {
            throw ValidationException::withMessages([
                'dose' => 'It is too early for this dose. You can confirm it from '.$earliest->format('g:i A').'.',
            ]);
        }

        $dose->update(['status' => DoseLog::TAKEN, 'confirmed_at' => $now]);

        // Taking a dose ends any missed-doses streak the contacts and doctor were told about.
        $this->emergency->resolveOnTaken($dose->patient);

        return $dose;
    }

    /** "Remind me later": the reminder repeats after a short delay, within limits. */
    public function snooze(DoseLog $dose): DoseLog
    {
        $now = CarbonImmutable::now();
        $until = $now->addMinutes((int) config('medimind.dose.snooze_minutes'));
        $deadline = $dose->scheduled_at->addMinutes((int) config('medimind.dose.grace_minutes'));

        $this->ensurePending($dose);

        if ($dose->scheduled_at->gt($now)) {
            throw ValidationException::withMessages(['dose' => 'This dose is not due yet.']);
        }
        if ($dose->snoozed_count >= (int) config('medimind.dose.max_snoozes')) {
            throw ValidationException::withMessages(['dose' => 'You have already snoozed this dose the maximum number of times.']);
        }
        if ($until->gt($deadline)) {
            throw ValidationException::withMessages(['dose' => 'It is too late to snooze this dose. Please take it now or skip it.']);
        }

        $dose->update([
            'snoozed_until' => $until,
            'snoozed_count' => $dose->snoozed_count + 1,
            'reminded_at' => null, // makes the dose eligible for a new reminder
        ]);

        return $dose;
    }

    /**
     * Pending doses past the grace window become missed. Patients who just missed a
     * dose get their risk re-scored and the emergency rule checked. Returns how many doses.
     */
    public function markMissed(): int
    {
        $cutoff = CarbonImmutable::now()->subMinutes((int) config('medimind.dose.grace_minutes'));

        $overdue = DoseLog::pending()->where('scheduled_at', '<', $cutoff);
        $patientIds = (clone $overdue)->distinct()->pluck('patient_id');

        $count = $overdue->update(['status' => DoseLog::MISSED]);

        User::whereIn('id', $patientIds)->each(function (User $patient) {
            $this->risk->assess($patient);
            $this->emergency->evaluate($patient);
        });

        return $count;
    }

    /**
     * Send a reminder for every due pending dose that has not been reminded.
     * Each dose is claimed with a conditional update first, so overlapping
     * runs can never send the same reminder twice. Returns how many were sent.
     */
    public function sendDueReminders(): int
    {
        $now = CarbonImmutable::now();
        $sent = 0;

        DoseLog::pending()
            ->where('scheduled_at', '<=', $now)
            ->where('scheduled_at', '>=', $now->subMinutes((int) config('medimind.dose.grace_minutes')))
            ->whereNull('reminded_at')
            ->where(fn ($q) => $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', $now))
            ->with(['medicine', 'patient'])
            ->orderBy('scheduled_at')
            ->each(function (DoseLog $dose) use ($now, &$sent) {
                $claimed = DoseLog::whereKey($dose->id)->whereNull('reminded_at')->update(['reminded_at' => $now]);

                if ($claimed) {
                    $dose->patient->notify(new DoseReminder($dose));
                    $sent++;
                }
            });

        return $sent;
    }

    private function ensurePending(DoseLog $dose): void
    {
        if (! $dose->isPending()) {
            throw ValidationException::withMessages([
                'dose' => $dose->status === DoseLog::TAKEN
                    ? 'This dose is already recorded as taken.'
                    : 'This dose was marked as missed and can no longer be changed.',
            ]);
        }
    }
}
