<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\PatientRoutine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoutineController extends Controller
{
    public function edit(Request $request): View
    {
        return view('patient.routine', ['routine' => $request->user()->routineOrDefault()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(
            collect(array_keys(PatientRoutine::DEFAULTS))->mapWithKeys(fn ($f) => [$f => ['required', 'date_format:H:i']])->all()
        );

        $request->user()->routine()->updateOrCreate([], $data);

        return redirect()->route('patient.medicines.index')
            ->with('status', 'Daily routine saved. It is used to suggest times when you set a schedule.');
    }
}
