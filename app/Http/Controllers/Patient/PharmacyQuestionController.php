<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PharmacyQuestionController extends Controller
{
    public function index(Request $request): View
    {
        $patient = $request->user();

        return view('patient.ask', [
            'pharmacies' => Pharmacy::whereHas('user', fn ($q) => $q->whereNotNull('approved_at'))->orderBy('name')->get(['id', 'name', 'address']),
            'medicines' => $patient->medicines()->active()->orderBy('name')->get(['id', 'name']),
            'questions' => $patient->pharmacyQuestions()->with(['pharmacy:id,name', 'medicine:id,name'])->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $patient = $request->user();

        $data = $request->validate([
            // Only approved pharmacies can be asked.
            'pharmacy_id' => ['required', Rule::exists('pharmacies', 'id')->where(
                fn ($q) => $q->whereIn('user_id', User::whereNotNull('approved_at')->select('id'))
            )],
            // Only one of the patient's own medicines can be mentioned.
            'patient_medicine_id' => ['nullable', Rule::exists('patient_medicines', 'id')->where('patient_id', $patient->id)],
            'question' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $patient->pharmacyQuestions()->create($data);

        return back()->with('status', 'Your question was sent. You will get a notification when the pharmacy replies.');
    }
}
