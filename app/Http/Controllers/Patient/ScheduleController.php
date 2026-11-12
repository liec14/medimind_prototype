<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Concerns\BuildsScheduleForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleRequest;
use App\Models\PatientMedicine;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    use BuildsScheduleForm;

    public function __construct(private ScheduleService $schedules) {}

    public function edit(Request $request, PatientMedicine $medicine): View
    {
        Gate::authorize('update', $medicine);

        return view('patient.schedule', $this->scheduleFormData($request, $medicine, $this->schedules));
    }

    public function update(ScheduleRequest $request, PatientMedicine $medicine): RedirectResponse
    {
        Gate::authorize('update', $medicine);

        $this->schedules->saveSchedule($medicine, $request->validated('frequency'), $request->validated('times'), $request->validated('days'));

        return redirect()->route('patient.medicines.index')->with('status', 'Schedule saved for '.$medicine->name.'.');
    }

    public function destroy(PatientMedicine $medicine): RedirectResponse
    {
        Gate::authorize('update', $medicine);

        $this->schedules->removeSchedule($medicine);

        return redirect()->route('patient.medicines.index')->with('status', 'Schedule removed for '.$medicine->name.'.');
    }
}
