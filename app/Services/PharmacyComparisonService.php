<?php

namespace App\Services;

use App\Models\CatalogMedicine;
use App\Models\PharmacyStock;
use Illuminate\Support\Collection;

/** Compares one medicine across the approved pharmacies registered in MediMind. */
class PharmacyComparisonService
{
    /**
     * In-stock pharmacies first, cheapest first; out-of-stock pharmacies after.
     * Pharmacies still waiting for admin approval are left out.
     *
     * @return Collection<int, PharmacyStock>
     */
    public function compare(CatalogMedicine $medicine): Collection
    {
        return PharmacyStock::where('catalog_medicine_id', $medicine->id)
            ->whereHas('pharmacy.user', fn ($q) => $q->whereNotNull('approved_at'))
            ->with('pharmacy')
            ->get()
            ->sortBy([['in_stock', 'desc'], ['price', 'asc']])
            ->values();
    }

    /** The cheapest in-stock offer, if any. */
    public function cheapest(Collection $rows): ?PharmacyStock
    {
        return $rows->firstWhere('in_stock', true);
    }
}
