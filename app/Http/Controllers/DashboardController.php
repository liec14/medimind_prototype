<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Send each user to the home screen for their role. */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isApproved()) {
            return redirect()->route('pending');
        }

        return redirect()->route(match ($user->role) {
            Role::Patient => 'patient.dashboard',
            Role::Doctor => 'doctor.dashboard',
            Role::Pharmacy => 'pharmacy.dashboard',
            Role::Admin => 'admin.approvals',
        });
    }
}
