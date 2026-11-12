<?php

namespace App\Services;

use App\Models\DoseLog;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Adherence = confirmed intakes / scheduled intakes x 100 (as in the paper).
 * Only doses whose outcome is known count: taken or missed. Pending doses
 * (still inside the grace window, or in the future) are not yet scheduled-and-over.
 */
class AdherenceService
{
    /** @return array{taken:int, missed:int, pending:int, rate:?float} */
    public function summary(User $patient, CarbonInterface $from, CarbonInterface $to): array
    {
        $counts = DoseLog::where('patient_id', $patient->id)
            ->whereBetween('scheduled_at', [$from, $to])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->shape((int) ($counts[DoseLog::TAKEN] ?? 0), (int) ($counts[DoseLog::MISSED] ?? 0), (int) ($counts[DoseLog::PENDING] ?? 0));
    }

    /**
     * The same summary for many patients in one query, keyed by patient id.
     * Patients with no doses in the period get an empty summary.
     *
     * @param  iterable<int>  $patientIds
     * @return array<int, array{taken:int, missed:int, pending:int, rate:?float}>
     */
    public function summariesFor(iterable $patientIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $ids = collect($patientIds)->unique()->values();

        $rows = DoseLog::whereIn('patient_id', $ids)
            ->whereBetween('scheduled_at', [$from, $to])
            ->selectRaw('patient_id, status, count(*) as total')
            ->groupBy('patient_id', 'status')
            ->get()
            ->groupBy('patient_id');

        return $ids->mapWithKeys(function ($id) use ($rows) {
            $byStatus = ($rows[$id] ?? collect())->pluck('total', 'status');

            return [$id => $this->shape((int) ($byStatus[DoseLog::TAKEN] ?? 0), (int) ($byStatus[DoseLog::MISSED] ?? 0), (int) ($byStatus[DoseLog::PENDING] ?? 0))];
        })->all();
    }

    /**
     * Adherence per calendar week (Monday to Sunday), oldest first, ending with this week.
     *
     * @return list<array{label:string, taken:int, missed:int, pending:int, rate:?float}>
     */
    public function weekly(User $patient, int $weeks = 8): array
    {
        // Immutable, so the week arithmetic below cannot shift the date it started from.
        $thisWeek = now()->toImmutable()->startOfWeek();
        $start = $thisWeek->subWeeks($weeks - 1);

        $logs = DoseLog::where('patient_id', $patient->id)
            ->whereIn('status', [DoseLog::TAKEN, DoseLog::MISSED])
            ->where('scheduled_at', '>=', $start)
            ->get(['scheduled_at', 'status'])
            ->groupBy(fn (DoseLog $d) => $d->scheduled_at->startOfWeek()->toDateString());

        $result = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->addWeeks($i);
            $group = $logs->get($weekStart->toDateString(), collect());
            $row = $this->shape($group->where('status', DoseLog::TAKEN)->count(), $group->where('status', DoseLog::MISSED)->count(), 0);
            $row['label'] = $weekStart->format('M j').' – '.$weekStart->addDays(6)->format('M j');
            $result[] = $row;
        }

        return $result;
    }

    private function shape(int $taken, int $missed, int $pending): array
    {
        $known = $taken + $missed;

        return [
            'taken' => $taken,
            'missed' => $missed,
            'pending' => $pending,
            'rate' => $known > 0 ? round($taken / $known * 100, 1) : null,
        ];
    }
}
