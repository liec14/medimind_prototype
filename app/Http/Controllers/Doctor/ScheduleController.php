<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Concerns\BuildsScheduleForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleRequest;
use App\Models\PatientMedicine;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** A doctor can correct the dosage schedule a linked patient registered. */
class ScheduleController extends Controller
{
    use BuildsScheduleForm;

    public function __construct(private ScheduleService $schedules) {}

    public function edit(Request $request, User $patient, PatientMedicine $medicine): View
    {
        Gate::authorize('update', $medicine);

        return view('doctor.schedule', ['patient' => $patient] + $this->scheduleFormData($request, $medicine, $this->schedules));
    }

    public function update(ScheduleRequest $request, User $patient, PatientMedicine $medicine): RedirectResponse
    {
        Gate::authorize('update', $medicine);

        $this->schedules->saveSchedule($medicine, $request->validated('frequency'), $request->validated('times'), $request->validated('days'));

        return redirect()->route('doctor.patients.show', $patient)->with('status', 'Schedule updated.');
    }
}
