<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\CatalogMedicine;
use App\Models\MedicineCategory;
use App\Services\InteractionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MedicineInfoController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
        ]);
        $q = trim($filters['q'] ?? '');

        $medicines = CatalogMedicine::with('category')
            ->when($q !== '', fn ($query) => $query->where(
                fn ($w) => $w->where('generic_name', 'like', "%{$q}%")->orWhere('brand_names', 'like', "%{$q}%")
            ))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->orderBy('generic_name')
            ->get();

        return view('patient.info.index', [
            'medicines' => $medicines,
            'categories' => MedicineCategory::orderBy('name')->get(),
            'filters' => ['q' => $q, 'category_id' => $filters['category_id'] ?? ''],
        ]);
    }

    public function show(Request $request, CatalogMedicine $catalogMedicine, InteractionService $interactions): View
    {
        $patient = $request->user();
        $activeIds = $interactions->activeCatalogIds($patient);

        return view('patient.info.show', [
            'medicine' => $catalogMedicine->load('category'),
            'alreadyTaking' => in_array($catalogMedicine->id, $activeIds, true),
            'interactions' => $interactions->between($catalogMedicine->id, $activeIds),
        ]);
    }
}
