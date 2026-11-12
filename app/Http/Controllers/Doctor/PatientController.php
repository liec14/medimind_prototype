<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdherenceService;
use App\Services\InteractionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function dashboard(Request $request, AdherenceService $adherence): View
    {
        $doctor = $request->user();
        $patients = $doctor->patients()
            ->with('latestRisk')
            ->withCount(['alerts as open_alerts' => fn ($q) => $q->whereNull('resolved_at')])
            ->get();

        // Patients who need attention first: high risk, then medium, then low / not scored.
        $rank = fn (User $p) => ['high' => 0, 'medium' => 1, 'low' => 2][$p->latestRisk?->level] ?? 3;

        $active = $patients->filter(fn ($p) => $p->pivot->status === 'active')
            ->sortBy([[fn ($a, $b) => $rank($a) <=> $rank($b)], [fn ($a, $b) => $b->open_alerts <=> $a->open_alerts], ['name', 'asc']])
            ->values();

        return view('doctor.dashboard', [
            'pending' => $patients->filter(fn ($p) => $p->pivot->status === 'pending'),
            'active' => $active,
            // One query for everyone's last 14 days, not one per patient.
            'rates' => $adherence->summariesFor($active->pluck('id'), now()->subDays(13)->startOfDay(), now()),
            'totals' => [
                'patients' => $active->count(),
                'high' => $active->filter(fn ($p) => $p->latestRisk?->hasEnoughData() && $p->latestRisk->level === 'high')->count(),
                'alerts' => (int) $active->sum('open_alerts'),
            ],
        ]);
    }

    public function show(User $patient, InteractionService $interactions, AdherenceService $adherence): View
    {
        Gate::authorize('viewRecord', $patient);

        return view('doctor.patient', [
            'patient' => $patient,
            'rate14' => $adherence->summary($patient, now()->subDays(13)->startOfDay(), now()),
            'rate30' => $adherence->summary($patient, now()->subDays(29)->startOfDay(), now()),
            'weeks' => $adherence->weekly($patient),
            'recentDoses' => $patient->doseLogs()->where('scheduled_at', '<=', now())->with('medicine')->latest('scheduled_at')->limit(8)->get(),
            'risk' => $patient->latestRisk,
            'alerts' => $patient->alerts()->with('acknowledgedBy')->latest()->limit(10)->get(),
            'interactions' => $interactions->forPatient($patient),
            'medicines' => $patient->medicines()->with(['category', 'updatedBy', 'schedule'])->orderByDesc('active')->orderBy('name')->get(),
        ]);
    }

    /** Accept or decline a patient's link request. */
    public function respond(Request $request, User $patient): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:accept,decline']]);

        $link = $request->user()->patients()->wherePivot('status', 'pending')->where('users.id', $patient->id)->first();
        abort_unless($link, 404);

        $request->user()->patients()->updateExistingPivot($patient->id, [
            'status' => $data['decision'] === 'accept' ? 'active' : 'declined',
        ]);

        return back()->with('status', $data['decision'] === 'accept' ? 'Patient added.' : 'Request declined.');
    }
}
