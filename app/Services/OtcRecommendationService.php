<?php

namespace App\Services;

use App\Models\DrugInteraction;
use App\Models\OtcRule;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Suggests non-prescription medicines for everyday complaints. Guidance only.
 *
 * Suggestions that have a HIGH-severity interaction with something the patient
 * already takes are moved to "not suggested" with the reason. Moderate
 * interactions stay in the list with a caution.
 */
class OtcRecommendationService
{
    public function __construct(private InteractionService $interactions) {}

    /** The complaints a patient can pick from. */
    public function symptoms(): Collection
    {
        return OtcRule::query()->orderBy('symptom')->distinct()->pluck('symptom');
    }

    /**
     * @return array{
     *   matched: list<string>,
     *   see_doctor_if: array<string, string>,
     *   suggestions: Collection,
     *   excluded: Collection
     * }
     */
    public function recommend(User $patient, ?string $symptom, ?string $text): array
    {
        $rules = $this->matchingRules($symptom, $text);

        $activeIds = $this->interactions->activeCatalogIds($patient);

        $suggestions = collect();
        $excluded = collect();

        foreach ($rules->groupBy('catalog_medicine_id') as $group) {
            /** @var OtcRule $rule */
            $rule = $group->sortBy('priority')->first();
            $medicine = $rule->catalogMedicine;
            $found = $this->interactions->between($medicine->id, $activeIds);

            $entry = [
                'medicine' => $medicine,
                'priority' => $rule->priority,
                'notes' => $rule->notes,
                'symptoms' => $group->pluck('symptom')->unique()->values()->all(),
                'already_taking' => in_array($medicine->id, $activeIds, true),
                'cautions' => $found->where('severity', DrugInteraction::MODERATE)->values(),
            ];

            if ($found->contains('severity', DrugInteraction::HIGH)) {
                $excluded->push($entry + ['reasons' => $found->where('severity', DrugInteraction::HIGH)->values()]);
            } else {
                $suggestions->push($entry);
            }
        }

        return [
            'matched' => $rules->pluck('symptom')->unique()->values()->all(),
            'see_doctor_if' => $rules->pluck('see_doctor_if', 'symptom')->filter()->all(),
            'suggestions' => $suggestions->sortBy('priority')->values(),
            'excluded' => $excluded->values(),
        ];
    }

    /** Rules for an exact complaint and/or any word of the free text, as one de-duplicated set. */
    private function matchingRules(?string $symptom, ?string $text): Collection
    {
        $all = OtcRule::with('catalogMedicine')->get();
        $terms = collect(preg_split('/[\s,;]+/', mb_strtolower((string) $text)))
            ->filter(fn ($w) => mb_strlen($w) >= 3)
            ->unique()->values()->all();

        return $all->filter(fn (OtcRule $r) => ($symptom && $r->symptom === $symptom) || ($terms && $r->matchesAny($terms)))
            ->filter(fn (OtcRule $r) => $r->catalogMedicine->is_otc)
            ->values();
    }
}
