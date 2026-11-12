<?php

namespace App\Services;

use App\Models\DrugInteraction;
use App\Models\PatientMedicine;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Known drug-pair interactions from the reference catalog. This is guidance
 * only: it covers the pairs in the catalog, not every possible interaction.
 *
 * Each result is ['severity' => 'high'|'moderate', 'note' => string, 'medicines' => [nameA, nameB]].
 */
class InteractionService
{
    /** Interactions between any two medicines in this set of catalog ids. */
    public function among(array $catalogIds): Collection
    {
        $ids = array_values(array_unique($catalogIds));
        if (count($ids) < 2) {
            return collect();
        }

        return $this->format(
            DrugInteraction::whereIn('medicine_a_id', $ids)->whereIn('medicine_b_id', $ids)->with(['medicineA', 'medicineB'])->get()
        );
    }

    /** Interactions between one medicine and a set of others. */
    public function between(int $catalogId, array $otherIds): Collection
    {
        $others = array_values(array_diff(array_unique($otherIds), [$catalogId]));
        if (! $others) {
            return collect();
        }

        return $this->format(
            DrugInteraction::query()
                ->where(fn ($q) => $q->where('medicine_a_id', $catalogId)->whereIn('medicine_b_id', $others))
                ->orWhere(fn ($q) => $q->where('medicine_b_id', $catalogId)->whereIn('medicine_a_id', $others))
                ->with(['medicineA', 'medicineB'])
                ->get()
        );
    }

    /** Interactions among everything a patient is currently taking. */
    public function forPatient(User $patient): Collection
    {
        return $this->among($this->activeCatalogIds($patient));
    }

    /** Interactions between a just-saved medicine and the patient's other active medicines. */
    public function forMedicine(PatientMedicine $medicine): Collection
    {
        if (! $medicine->active || ! $medicine->catalog_medicine_id) {
            return collect();
        }

        return $this->between($medicine->catalog_medicine_id, $this->activeCatalogIds($medicine->patient, except: $medicine->id));
    }

    /** @return list<int> catalog ids of the patient's active medicines that are linked to the catalog */
    public function activeCatalogIds(User $patient, ?int $except = null): array
    {
        return $patient->medicines()->active()
            ->whereNotNull('catalog_medicine_id')
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->pluck('catalog_medicine_id')->unique()->values()->all();
    }

    private function format(Collection $interactions): Collection
    {
        return $interactions
            ->map(fn (DrugInteraction $i) => [
                'severity' => $i->severity,
                'note' => $i->note,
                'medicines' => [$i->medicineA->generic_name, $i->medicineB->generic_name],
            ])
            ->sortBy(fn ($i) => $i['severity'] === DrugInteraction::HIGH ? 0 : 1)
            ->values();
    }
}
