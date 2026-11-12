<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DoseHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** A linked doctor reads a patient's intake history. Doctors cannot change it. */
class HistoryController extends Controller
{
    public function __invoke(Request $request, User $patient, DoseHistoryService $history): View
    {
        Gate::authorize('viewRecord', $patient);

        $filters = $request->validate(DoseHistoryService::RULES);

        return view('doctor.history', ['patient' => $patient] + $history->build($patient, $filters));
    }
}
