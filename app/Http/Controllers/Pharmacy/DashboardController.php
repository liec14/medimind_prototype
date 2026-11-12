<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Services\RefillService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, RefillService $refills): View
    {
        $pharmacy = $request->user()->pharmacy;
        abort_unless($pharmacy, 403);

        $due = $refills->dueFor($pharmacy);

        return view('pharmacy.dashboard', [
            'pharmacy' => $pharmacy,
            'due' => $due,
            'stopped' => $due->where('status', 'stopped')->count(),
            'openQuestions' => $pharmacy->questions()->whereNull('answered_at')->count(),
            'listed' => $pharmacy->stock()->count(),
            'patients' => $pharmacy->consentingPatients()->count(),
        ]);
    }
}
