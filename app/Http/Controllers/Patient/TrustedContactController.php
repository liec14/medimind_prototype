<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\TrustedContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TrustedContactController extends Controller
{
    public function index(Request $request): View
    {
        return view('patient.contacts', [
            'contacts' => $request->user()->trustedContacts()->orderBy('name')->get(),
            'max' => (int) config('medimind.emergency.max_contacts'),
            'misses' => (int) config('medimind.emergency.consecutive_misses'),
            'doctors' => $request->user()->activeDoctors(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $patient = $request->user();

        if ($patient->trustedContacts()->count() >= (int) config('medimind.emergency.max_contacts')) {
            return back()->withErrors(['email' => 'You can add up to '.config('medimind.emergency.max_contacts').' trusted contacts. Remove one first.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:50'],
        ]);

        if ($patient->trustedContacts()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'That email is already on your list.'])->withInput();
        }

        $patient->trustedContacts()->create($data + ['notify' => true]);

        return back()->with('status', $data['name'].' added.');
    }

    /** Turn alerts to this person on or off. */
    public function update(Request $request, TrustedContact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $contact->update(['notify' => $request->boolean('notify')]);

        return back()->with('status', $contact->notify ? $contact->name.' will be alerted.' : $contact->name.' will not be alerted.');
    }

    public function destroy(TrustedContact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        $contact->delete();

        return back()->with('status', 'Contact removed.');
    }
}
