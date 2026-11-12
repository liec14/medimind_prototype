<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\CatalogMedicine;
use App\Services\PharmacyComparisonService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyComparisonController extends Controller
{
    public function __invoke(Request $request, PharmacyComparisonService $comparison): View
    {
        $input = $request->validate(['medicine_id' => ['nullable', 'integer', 'exists:medicines_catalog,id']]);

        $selected = isset($input['medicine_id']) ? CatalogMedicine::find($input['medicine_id']) : null;
        $rows = $selected ? $comparison->compare($selected) : collect();

        return view('patient.compare', [
            'catalog' => CatalogMedicine::orderBy('generic_name')->get(['id', 'generic_name', 'brand_names', 'strength', 'form']),
            'selected' => $selected,
            'rows' => $rows,
            'cheapest' => $comparison->cheapest($rows),
        ]);
    }
}
