<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyConsentController extends Controller
{
    public function index(Request $request): View
    {
        $pharmacies = Pharmacy::whereHas('user', fn ($q) => $q->whereNotNull('approved_at'))->orderBy('name')->get();

        return view('patient.pharmacies', [
            'pharmacies' => $pharmacies,
            'consented' => $request->user()->consentedPharmacies()->pluck('pharmacies.id')->all(),
        ]);
    }

    public function store(Request $request, Pharmacy $pharmacy): RedirectResponse
    {
        abort_unless($pharmacy->user->isApproved(), 404);

        $request->user()->consentedPharmacies()->syncWithoutDetaching([$pharmacy->id]);

        return back()->with('status', 'Sharing enabled with '.$pharmacy->name.'.');
    }

    public function destroy(Request $request, Pharmacy $pharmacy): RedirectResponse
    {
        $request->user()->consentedPharmacies()->detach($pharmacy->id);

        return back()->with('status', 'Sharing stopped with '.$pharmacy->name.'.');
    }
}
