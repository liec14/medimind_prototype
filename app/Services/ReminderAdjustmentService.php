<?php

namespace App\Services;

use App\Models\ReminderSuggestion;
use App\Models\User;

/**
 * Turns "you often miss your evening doses" into a one-tap suggestion to move
 * those reminders earlier. Nothing changes until the patient accepts.
 */
class ReminderAdjustmentService
{
    public function __construct(private ScheduleService $schedules) {}

    /**
     * Create suggestions for medicines that have a dose in a problem time of day,
     * and drop pending ones for slots that are no longer a problem. A suggestion the
     * patient already answered is not repeated for 30 days.
     *
     * @param  list<array{slot: string}>  $problemSlots
     */
    public function sync(User $patient, array $problemSlots): void
    {
        $slots = collect($problemSlots)->pluck('slot')->all();
        $shift = (int) config('medimind.reminder_shift_minutes');

        $medicines = $patient->medicines()->active()->with('schedule')->get();

        ReminderSuggestion::whereIn('patient_medicine_id', $medicines->pluck('id'))
            ->where('status', ReminderSuggestion::PENDING)
            ->when($slots, fn ($q) => $q->whereNotIn('slot', $slots))
            ->delete();

        foreach ($medicines as $medicine) {
            foreach ($slots as $slot) {
                if (! $this->timesInSlot($medicine->schedule?->times ?? [], $slot)) {
                    continue;
                }

                $existing = ReminderSuggestion::where('patient_medicine_id', $medicine->id)->where('slot', $slot)->first();

                if (! $existing) {
                    ReminderSuggestion::create(['patient_medicine_id' => $medicine->id, 'slot' => $slot, 'shift_minutes' => $shift, 'status' => ReminderSuggestion::PENDING]);
                } elseif ($existing->status !== ReminderSuggestion::PENDING && $existing->updated_at->lt(now()->subDays(30))) {
                    $existing->update(['status' => ReminderSuggestion::PENDING, 'shift_minutes' => $shift]);
                }
            }
        }
    }

    /** Move this medicine's reminders in the slot earlier, then rebuild its upcoming doses. */
    public function accept(ReminderSuggestion $suggestion): void
    {
        $medicine = $suggestion->medicine()->with('schedule')->firstOrFail();
        $schedule = $medicine->schedule;

        if ($schedule) {
            $times = collect($schedule->times)->map(function (string $time) use ($suggestion) {
                if (AdherenceRiskService::slotFor((int) substr($time, 0, 2)) !== $suggestion->slot) {
                    return $time;
                }

                return $this->shift($time, $suggestion->shift_minutes);
            })->all();

            $this->schedules->saveSchedule($medicine, $schedule->frequency, $times, $schedule->days_of_week);
        }

        $suggestion->update(['status' => ReminderSuggestion::ACCEPTED]);
    }

    public function dismiss(ReminderSuggestion $suggestion): void
    {
        $suggestion->update(['status' => ReminderSuggestion::DISMISSED]);
    }

    /** "20:00" minus 30 minutes is "19:30". It never crosses midnight; it stops at 00:00. */
    private function shift(string $time, int $minutes): string
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        $total = max(0, $h * 60 + $m - $minutes);

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    private function timesInSlot(array $times, string $slot): bool
    {
        foreach ($times as $time) {
            if (AdherenceRiskService::slotFor((int) substr($time, 0, 2)) === $slot) {
                return true;
            }
        }

        return false;
    }
}
