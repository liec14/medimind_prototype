<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AlertController extends Controller
{
    /** "I have seen this." The alert stays open until it is resolved. */
    public function acknowledge(Request $request, Alert $alert): RedirectResponse
    {
        Gate::authorize('manage', $alert);

        if ($alert->acknowledged_at === null) {
            $alert->forceFill(['acknowledged_by' => $request->user()->id, 'acknowledged_at' => now()])->save();
        }

        return back()->with('status', 'Alert acknowledged.');
    }

    /** "I followed up." Closes the alert. */
    public function resolve(Request $request, Alert $alert): RedirectResponse
    {
        Gate::authorize('manage', $alert);

        if ($alert->isOpen()) {
            $alert->forceFill([
                'acknowledged_by' => $alert->acknowledged_by ?? $request->user()->id,
                'acknowledged_at' => $alert->acknowledged_at ?? now(),
            ])->save();
            $alert->resolve('doctor');
        }

        return back()->with('status', 'Alert resolved.');
    }
}
