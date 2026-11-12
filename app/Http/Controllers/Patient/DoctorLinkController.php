<?php

namespace App\Http\Controllers\Patient;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoctorLinkController extends Controller
{
    public function index(Request $request): View
    {
        return view('patient.doctors', ['doctors' => $request->user()->doctors()->get()]);
    }

    /** Invite an approved doctor by email; the doctor must accept. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $doctor = User::where('email', $data['email'])
            ->where('role', Role::Doctor)
            ->whereNotNull('approved_at')
            ->first();

        if (! $doctor) {
            return back()->withErrors(['email' => 'No approved doctor was found with that email.'])->onlyInput('email');
        }

        $patient = $request->user();
        $existing = $patient->doctors()->where('users.id', $doctor->id)->first();

        if ($existing && $existing->pivot->status === 'active') {
            return back()->with('status', 'Dr. '.$doctor->name.' is already linked to you.');
        }

        if ($existing) {
            $patient->doctors()->updateExistingPivot($doctor->id, ['status' => 'pending']);
        } else {
            $patient->doctors()->attach($doctor->id, ['status' => 'pending']);
        }

        return back()->with('status', 'Invitation sent. Dr. '.$doctor->name.' must accept it before seeing your records.');
    }

    public function destroy(Request $request, User $doctor): RedirectResponse
    {
        $request->user()->doctors()->detach($doctor->id);

        return back()->with('status', 'Doctor access removed.');
    }
}
