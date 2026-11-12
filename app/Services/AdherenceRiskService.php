<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\DoseLog;
use App\Models\RiskAssessment;
use App\Models\User;
use App\Notifications\CareAlertNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Transparent, rule-based estimate of how likely a patient is to struggle with
 * their medicines (0-100). Every factor adds points up to a weight set in
 * config/medimind.php, and the reasons are stored with the score so a doctor can
 * see why a patient was flagged. It supports follow-up; it is not a diagnosis.
 *
 * The weights are hand-set starting values, not trained on data. The paper's
 * design replaces this with a machine-learning classifier once enough real
 * intake data exists.
 */
class AdherenceRiskService
{
    public function __construct(private ReminderAdjustmentService $adjustments) {}

    /** Time-of-day bucket for an hour (0-23). */
    public static function slotFor(int $hour): string
    {
        return match (true) {
            $hour >= 5 && $hour < 11 => 'morning',
            $hour >= 11 && $hour < 17 => 'afternoon',
            $hour >= 17 && $hour < 21 => 'evening',
            default => 'night',
        };
    }

    /**
     * Score a patient from their recent intake history. Does not save anything.
     *
     * @return array{score: float, level: string, sufficient_data: bool, decided_doses: int, factors: list<array<string, mixed>>, problem_slots: list<array<string, mixed>>}
     */
    public function compute(User $patient): array
    {
        $cfg = config('medimind.risk');
        $now = CarbonImmutable::now();

        $logs30 = DoseLog::where('patient_id', $patient->id)
            ->whereIn('status', [DoseLog::TAKEN, DoseLog::MISSED])
            ->whereBetween('scheduled_at', [$now->subDays(30)->startOfDay(), $now])
            ->orderBy('scheduled_at')
            ->get(['id', 'scheduled_at', 'status', 'snoozed_count']);

        $cut14 = $now->subDays(14)->startOfDay();
        $logs14 = $logs30->filter(fn (DoseLog $d) => $d->scheduled_at->gte($cut14))->values();

        if ($logs14->count() < $cfg['min_doses']) {
            return ['score' => 0.0, 'level' => 'low', 'sufficient_data' => false, 'decided_doses' => $logs14->count(), 'factors' => [], 'problem_slots' => []];
        }

        $w = $cfg['weights'];
        $factors = [];

        // 1. Share of doses taken over the last 14 days.
        $taken = $logs14->where('status', DoseLog::TAKEN)->count();
        $rate = $taken / $logs14->count() * 100;
        $factors[] = $this->factor('adherence', 'Doses taken (last 14 days)', (100 - $rate) / 100 * $w['adherence'], $w['adherence'],
            "Took {$taken} of {$logs14->count()} doses (".round($rate).'%).');

        // 2. Doses missed in a row, counting back from the most recent.
        $run = 0;
        foreach ($logs30->reverse() as $dose) {
            if ($dose->status !== DoseLog::MISSED) {
                break;
            }
            $run++;
        }
        $limit = max(1, (int) config('medimind.emergency.consecutive_misses'));
        $factors[] = $this->factor('streak', 'Missed in a row', min($run / $limit, 1) * $w['streak'], $w['streak'],
            $run === 0 ? 'The most recent dose was taken.' : "The last {$run} dose".($run > 1 ? 's were' : ' was').' missed.');

        // 3. A time of day that is often missed.
        [$slotFactor, $problemSlots] = $this->timeOfDayFactor($logs30, $cfg, $w['time_pattern']);
        $factors[] = $slotFactor;

        // 4. A weekday that is often missed.
        $factors[] = $this->weekdayFactor($logs30, $cfg, $w['weekday_pattern']);

        // 5. How many doses a day the regimen asks for.
        $factors[] = $this->complexityFactor($patient, $cfg, $w['complexity']);

        // 6. How often doses were snoozed before being taken.
        $avgSnooze = $logs14->avg('snoozed_count') ?? 0;
        $maxSnooze = max(1, (int) config('medimind.dose.max_snoozes'));
        $factors[] = $this->factor('snooze', 'Reminders snoozed', min($avgSnooze / $maxSnooze, 1) * $w['snooze'], $w['snooze'],
            'Doses were snoozed '.round($avgSnooze, 1).' time(s) on average.');

        $score = round(min(100, max(0, collect($factors)->sum('points'))), 1);

        return [
            'score' => $score,
            'level' => $score >= $cfg['high'] ? 'high' : ($score >= $cfg['medium'] ? 'medium' : 'low'),
            'sufficient_data' => true,
            'decided_doses' => $logs14->count(),
            'factors' => $factors,
            'problem_slots' => $problemSlots,
        ];
    }

    /**
     * Compute and save today's estimate, raise or clear the doctor-facing
     * "high risk" alert, and refresh reminder suggestions.
     */
    public function assess(User $patient): RiskAssessment
    {
        $result = $this->compute($patient);

        $assessment = RiskAssessment::updateOrCreate(
            ['patient_id' => $patient->id, 'assessed_on' => today()->toDateString()],
            ['score' => $result['score'], 'level' => $result['level'], 'factors' => $result, 'computed_at' => now()],
        );

        $this->syncRiskAlert($patient, $assessment);
        $this->adjustments->sync($patient, $result['problem_slots']);

        return $assessment;
    }

    /** Assess every patient who has at least one active medicine. Returns how many were assessed. */
    public function assessAll(): int
    {
        $count = 0;

        User::where('role', 'patient')->whereHas('medicines', fn ($q) => $q->active())
            ->each(function (User $patient) use (&$count) {
                $this->assess($patient);
                $count++;
            });

        return $count;
    }

    private function syncRiskAlert(User $patient, RiskAssessment $assessment): void
    {
        $open = Alert::open()->where('patient_id', $patient->id)->where('type', Alert::RISK)->first();

        if ($assessment->level === 'high' && ! $open) {
            $alert = Alert::create([
                'patient_id' => $patient->id,
                'type' => Alert::RISK,
                'level' => 'high',
                'message' => $patient->name.' is at high risk of poor adherence (score '.$assessment->score.'). A follow-up may help.',
                'data' => ['score' => $assessment->score],
            ]);

            foreach ($patient->activeDoctors() as $doctor) {
                $doctor->notify(new CareAlertNotification(
                    'High adherence risk: '.$patient->name,
                    $alert->message,
                    route('doctor.patients.show', $patient),
                    ['database'],
                ));
            }
        } elseif ($assessment->level !== 'high' && $open) {
            $open->resolve('improved');
        }
    }

    private function factor(string $key, string $label, float $points, float $max, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'points' => round($points, 1), 'max' => $max, 'detail' => $detail];
    }

    /** @return array{0: array<string, mixed>, 1: list<array<string, mixed>>} */
    private function timeOfDayFactor(Collection $logs, array $cfg, float $max): array
    {
        $problems = [];

        foreach ($logs->groupBy(fn (DoseLog $d) => self::slotFor($d->scheduled_at->hour)) as $slot => $group) {
            $missed = $group->where('status', DoseLog::MISSED)->count();
            if ($group->count() >= $cfg['slot_min_doses'] && $missed / $group->count() >= $cfg['pattern_miss_rate']) {
                $problems[] = ['slot' => $slot, 'missed' => $missed, 'total' => $group->count(), 'rate' => round($missed / $group->count(), 2)];
            }
        }
        usort($problems, fn ($a, $b) => $b['rate'] <=> $a['rate']);

        if (! $problems) {
            return [$this->factor('time_pattern', 'Time of day', 0, $max, 'No time of day is missed more than others.'), []];
        }

        $worst = $problems[0];

        return [$this->factor('time_pattern', 'Time of day', $worst['rate'] * $max, $max,
            "{$worst['missed']} of {$worst['total']} {$worst['slot']} doses were missed."), $problems];
    }

    private function weekdayFactor(Collection $logs, array $cfg, float $max): array
    {
        $worst = null;

        foreach ($logs->groupBy(fn (DoseLog $d) => $d->scheduled_at->dayOfWeek) as $group) {
            $missed = $group->where('status', DoseLog::MISSED)->count();
            $rate = $missed / $group->count();
            if ($group->count() >= $cfg['weekday_min_doses'] && $rate >= $cfg['pattern_miss_rate'] && (! $worst || $rate > $worst['rate'])) {
                $worst = ['day' => $group->first()->scheduled_at->format('l'), 'missed' => $missed, 'total' => $group->count(), 'rate' => $rate];
            }
        }

        return $worst
            ? $this->factor('weekday_pattern', 'Day of the week', $worst['rate'] * $max, $max, "{$worst['missed']} of {$worst['total']} doses on {$worst['day']}s were missed.")
            : $this->factor('weekday_pattern', 'Day of the week', 0, $max, 'No day of the week stands out.');
    }

    private function complexityFactor(User $patient, array $cfg, float $max): array
    {
        $medicines = $patient->medicines()->active()->with('schedule')->get();

        $perDay = $medicines->sum(function ($m) {
            if (! $m->schedule) {
                return 0;
            }
            $days = empty($m->schedule->days_of_week) ? 7 : count($m->schedule->days_of_week);

            return count($m->schedule->times) * $days / 7;
        });

        return $this->factor('complexity', 'Doses per day', min($perDay / $cfg['complexity_full_at'], 1) * $max, $max,
            round($perDay, 1).' doses a day across '.$medicines->count().' medicine'.($medicines->count() === 1 ? '' : 's').'.');
    }
}
