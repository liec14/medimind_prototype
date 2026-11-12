<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockRequest;
use App\Models\CatalogMedicine;
use App\Models\PharmacyStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        $pharmacy = $request->user()->pharmacy;
        abort_unless($pharmacy, 403);

        $stock = $pharmacy->stock()->with('catalogMedicine')->get()->sortBy(fn ($s) => $s->catalogMedicine->generic_name)->values();

        return view('pharmacy.prices', [
            'pharmacy' => $pharmacy,
            'stock' => $stock,
            'available' => CatalogMedicine::whereNotIn('id', $stock->pluck('catalog_medicine_id'))->orderBy('generic_name')->get(['id', 'generic_name', 'strength', 'form']),
        ]);
    }

    /** Add a medicine to the price list (or refresh it if it is already listed). */
    public function store(StockRequest $request): RedirectResponse
    {
        $pharmacy = $request->user()->pharmacy;
        abort_unless($pharmacy, 403);

        $data = $request->validated();
        $stock = $pharmacy->stock()->updateOrCreate(
            ['catalog_medicine_id' => $data['catalog_medicine_id']],
            ['price' => $data['price'], 'in_stock' => $data['in_stock']],
        );
        $stock->touch(); // "last confirmed" even when nothing changed

        return back()->with('status', 'Price list updated.');
    }

    public function update(StockRequest $request, PharmacyStock $stock): RedirectResponse
    {
        Gate::authorize('update', $stock);

        $stock->update($request->safe()->only(['price', 'in_stock']));
        $stock->touch();

        return back()->with('status', 'Updated '.$stock->catalogMedicine->generic_name.'.');
    }

    public function destroy(PharmacyStock $stock): RedirectResponse
    {
        Gate::authorize('delete', $stock);

        $stock->delete();

        return back()->with('status', 'Removed from your price list.');
    }
}
