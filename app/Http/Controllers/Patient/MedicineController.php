<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineRequest;
use App\Models\CatalogMedicine;
use App\Models\MedicineCategory;
use App\Models\PatientMedicine;
use App\Services\InteractionService;
use App\Services\MedicineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MedicineController extends Controller
{
    public function __construct(private MedicineService $medicines, private InteractionService $interactions) {}

    public function index(Request $request): View
    {
        $all = $request->user()->medicines()->with(['category', 'schedule'])->orderBy('name')->get();

        return view('patient.medicines.index', [
            'active' => $all->where('active', true),
            'inactive' => $all->where('active', false),
            'interactions' => $this->interactions->forPatient($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        // ?name= lets the medicine info page offer "Add to my medicines".
        $medicine = new PatientMedicine(['active' => true, 'start_date' => today(), 'name' => $request->query('name')]);

        return view('patient.medicines.create', $this->formData($medicine));
    }

    public function store(MedicineRequest $request): RedirectResponse
    {
        $medicine = $this->medicines->save(new PatientMedicine, $request->validated(), $request->user());

        return redirect()->route('patient.medicines.index')
            ->with('status', 'Medicine added.')
            ->with('new_interactions', $this->interactions->forMedicine($medicine)->count());
    }

    public function edit(PatientMedicine $medicine): View
    {
        Gate::authorize('update', $medicine);

        return view('patient.medicines.edit', $this->formData($medicine));
    }

    public function update(MedicineRequest $request, PatientMedicine $medicine): RedirectResponse
    {
        Gate::authorize('update', $medicine);

        $this->medicines->save($medicine, $request->validated(), $request->user());

        return redirect()->route('patient.medicines.index')
            ->with('status', 'Medicine updated.')
            ->with('new_interactions', $this->interactions->forMedicine($medicine)->count());
    }

    public function destroy(PatientMedicine $medicine): RedirectResponse
    {
        Gate::authorize('delete', $medicine);

        $medicine->delete();

        return redirect()->route('patient.medicines.index')->with('status', 'Medicine removed.');
    }

    private function formData(PatientMedicine $medicine): array
    {
        return [
            'medicine' => $medicine,
            'categories' => MedicineCategory::orderBy('name')->get(),
            'catalog' => CatalogMedicine::orderBy('generic_name')->get(['generic_name', 'brand_names']),
        ];
    }
}
