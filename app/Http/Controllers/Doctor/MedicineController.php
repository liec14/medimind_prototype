<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineRequest;
use App\Models\CatalogMedicine;
use App\Models\MedicineCategory;
use App\Models\PatientMedicine;
use App\Models\User;
use App\Services\MedicineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** A doctor verifies and corrects a linked patient's entries. It never prescribes. */
class MedicineController extends Controller
{
    public function __construct(private MedicineService $medicines) {}

    public function edit(User $patient, PatientMedicine $medicine): View
    {
        Gate::authorize('update', $medicine);

        return view('doctor.medicine-edit', [
            'patient' => $patient,
            'medicine' => $medicine,
            'categories' => MedicineCategory::orderBy('name')->get(),
            'catalog' => CatalogMedicine::orderBy('generic_name')->get(['generic_name', 'brand_names']),
        ]);
    }

    public function update(MedicineRequest $request, User $patient, PatientMedicine $medicine): RedirectResponse
    {
        Gate::authorize('update', $medicine);

        $this->medicines->save($medicine, $request->validated(), $request->user());

        return redirect()->route('doctor.patients.show', $patient)->with('status', 'Medicine details updated.');
    }
}
