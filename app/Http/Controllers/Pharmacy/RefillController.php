<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\PatientMedicine;
use App\Services\RefillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RefillController extends Controller
{
    public function __construct(private RefillService $refills) {}

    public function index(Request $request): View
    {
        $pharmacy = $request->user()->pharmacy;
        abort_unless($pharmacy, 403);

        $rows = $this->refills->dueFor($pharmacy)->map(fn ($row) => $row + [
            'default_message' => $this->refills->defaultMessage($pharmacy, $row['medicine']),
        ]);

        return view('pharmacy.refills', ['pharmacy' => $pharmacy, 'rows' => $rows]);
    }

    public function followUp(Request $request, PatientMedicine $medicine): RedirectResponse
    {
        $pharmacy = $request->user()->pharmacy;
        abort_unless($pharmacy, 403);

        // The patient must have agreed to share with this pharmacy.
        Gate::authorize('viewShared', $medicine->patient);
        // And the medicine must actually be on this pharmacy's refill list.
        abort_unless($this->refills->isDue($pharmacy, $medicine), 404);

        $data = $request->validate(['message' => ['required', 'string', 'max:500']]);

        $this->refills->sendFollowUp($pharmacy, $medicine, $data['message']);

        return back()->with('status', 'Message sent to '.$medicine->patient->name.'.');
    }
}
