<?php

namespace App\Services;

use App\Models\DoseLog;
use App\Models\User;

/** Dose history for one patient: filtered list, summary and weekly chart. Used by the patient and by linked doctors. */
class DoseHistoryService
{
    public const RULES = [
        'medicine_id' => ['nullable', 'integer'],
        'status' => ['nullable', 'in:taken,missed,pending'],
        'range' => ['nullable', 'in:7,30,all'],
    ];

    public function __construct(private AdherenceService $adherence) {}

    /**
     * @param  array{medicine_id?: ?int, status?: ?string, range?: ?string}  $filters  validated with RULES
     * @return array{doses: mixed, medicines: mixed, weeks: array, summary: array, filters: array}
     */
    public function build(User $patient, array $filters): array
    {
        $range = $filters['range'] ?? '30';
        $from = $range === 'all' ? null : now()->subDays((int) $range - 1)->startOfDay();

        $query = DoseLog::where('patient_id', $patient->id)
            ->when($from, fn ($q) => $q->where('scheduled_at', '>=', $from))
            // Scoped to this patient's own medicines, so an id from another patient matches nothing.
            ->when($filters['medicine_id'] ?? null, fn ($q, $id) => $q->where('patient_medicine_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->where('scheduled_at', '<=', now()->endOfDay());

        return [
            'doses' => $query->with('medicine')->orderByDesc('scheduled_at')->paginate(20)->withQueryString(),
            'medicines' => $patient->medicines()->orderBy('name')->get(['id', 'name']),
            'weeks' => $this->adherence->weekly($patient),
            'summary' => $this->adherence->summary($patient, $from ?? now()->subYears(20), now()),
            'filters' => ['medicine_id' => $filters['medicine_id'] ?? '', 'status' => $filters['status'] ?? '', 'range' => $range],
        ];
    }
}
