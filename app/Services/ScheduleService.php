<?php

namespace App\Services;

use App\Models\DoseLog;
use App\Models\PatientMedicine;
use App\Models\PatientRoutine;
use App\Models\Schedule;
use Carbon\CarbonImmutable;

class ScheduleService
{
    /**
     * Suggest dose times for a preset frequency from the patient's routine.
     * Returns an empty list for "custom".
     *
     * @return list<string>
     */
    public function suggestTimes(string $frequency, ?PatientRoutine $routine = null): array
    {
        $r = $routine ?? PatientRoutine::defaults();

        return match ($frequency) {
            'once_daily' => [$r->breakfast_time],
            'twice_daily' => [$r->breakfast_time, $r->dinner_time],
            'three_times_daily' => [$r->breakfast_time, $r->lunch_time, $r->dinner_time],
            'four_times_daily' => [$r->breakfast_time, $r->lunch_time, $r->dinner_time, $r->sleep_time],
            default => [],
        };
    }

    /** Save a medicine's schedule and rebuild its upcoming doses. */
    public function saveSchedule(PatientMedicine $medicine, string $frequency, array $times, ?array $daysOfWeek): Schedule
    {
        $times = collect($times)->filter()->unique()->sort()->values()->all();
        $days = collect($daysOfWeek ?? [])->map(fn ($d) => (int) $d)->unique()->sort()->values();
        // Selecting every day is the same as no restriction.
        $days = ($days->isEmpty() || $days->count() === 7) ? null : $days->all();

        $schedule = $medicine->schedule()->updateOrCreate([], [
            'frequency' => $frequency,
            'times' => $times,
            'days_of_week' => $days,
        ]);

        $medicine->setRelation('schedule', $schedule);
        $this->sync($medicine);

        return $schedule;
    }

    public function removeSchedule(PatientMedicine $medicine): void
    {
        $medicine->schedule()->delete();
        $medicine->unsetRelation('schedule');
        $this->sync($medicine);
    }

    /**
     * Bring a medicine's upcoming doses in line with its schedule and status:
     * drop future pending doses, then regenerate if it should still be running.
     * Taken and missed doses are history and are never touched.
     */
    public function sync(PatientMedicine $medicine, ?int $days = null): int
    {
        $this->purgeUpcoming($medicine);

        return $this->generateDoseLogs($medicine, $days);
    }

    public function purgeUpcoming(PatientMedicine $medicine): void
    {
        $medicine->doseLogs()
            ->where('status', DoseLog::PENDING)
            ->where('scheduled_at', '>=', now())
            ->delete();
    }

    /**
     * Create pending dose logs for the next $days days (today included).
     * Idempotent, and never creates a dose in the past, so setting a schedule
     * at 3 PM does not produce morning doses that count as missed.
     *
     * @return int number of doses created
     */
    public function generateDoseLogs(PatientMedicine $medicine, ?int $days = null, ?CarbonImmutable $from = null): int
    {
        $medicine->loadMissing('schedule');
        $schedule = $medicine->schedule;

        if (! $medicine->active || ! $schedule) {
            return 0;
        }

        $days ??= (int) config('medimind.dose_generate_days', 7);
        $now = $from ?? CarbonImmutable::now();
        $first = $now->startOfDay();
        if ($medicine->start_date->gt($first)) {
            $first = CarbonImmutable::instance($medicine->start_date)->startOfDay();
        }
        $last = $now->startOfDay()->addDays($days - 1);
        if ($medicine->end_date && $medicine->end_date->lt($last)) {
            $last = CarbonImmutable::instance($medicine->end_date)->startOfDay();
        }

        $created = 0;
        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            if (! $schedule->runsOn($day->dayOfWeek)) {
                continue;
            }

            foreach ($schedule->times as $time) {
                [$h, $m] = array_map('intval', explode(':', $time));
                $at = $day->setTime($h, $m);

                if ($at->lt($now)) {
                    continue;
                }

                $log = DoseLog::firstOrCreate(
                    ['patient_medicine_id' => $medicine->id, 'scheduled_at' => $at],
                    ['patient_id' => $medicine->patient_id, 'status' => DoseLog::PENDING],
                );
                $created += $log->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $created;
    }

    /** Top up the rolling window for every active medicine that has a schedule. */
    public function generateForAll(?int $days = null): int
    {
        $created = 0;

        PatientMedicine::active()->whereHas('schedule')->with('schedule')
            ->each(function (PatientMedicine $medicine) use ($days, &$created) {
                $created += $this->generateDoseLogs($medicine, $days);
            });

        return $created;
    }
}
