<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\DoseLog;
use App\Models\ReminderSuggestion;
use App\Services\AdherenceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AdherenceService $adherence): View
    {
        $patient = $request->user();

        $today = DoseLog::where('patient_id', $patient->id)
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
            ->with('medicine')
            ->orderBy('scheduled_at')
            ->get();

        return view('patient.dashboard', [
            'today' => $today,
            'counts' => [
                'taken' => $today->where('status', DoseLog::TAKEN)->count(),
                'missed' => $today->where('status', DoseLog::MISSED)->count(),
                'pending' => $today->where('status', DoseLog::PENDING)->count(),
            ],
            'week' => $adherence->summary($patient, now()->subDays(6)->startOfDay(), now()),
            'hasMedicines' => $patient->medicines()->active()->exists(),
            'suggestions' => ReminderSuggestion::where('status', ReminderSuggestion::PENDING)
                ->whereHas('medicine', fn ($q) => $q->where('patient_id', $patient->id)->where('active', true))
                ->with('medicine')->get(),
        ]);
    }
}
