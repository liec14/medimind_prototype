<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\DoseLog;
use App\Services\DoseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DoseController extends Controller
{
    public function __construct(private DoseService $doses) {}

    public function confirm(DoseLog $dose): RedirectResponse
    {
        Gate::authorize('update', $dose);

        $this->doses->confirm($dose);

        return back()->with('status', 'Recorded: you took '.$dose->medicine->name.'.');
    }

    public function snooze(DoseLog $dose): RedirectResponse
    {
        Gate::authorize('update', $dose);

        $this->doses->snooze($dose);

        return back()->with('status', 'OK, we will remind you about '.$dose->medicine->name.' in '.config('medimind.dose.snooze_minutes').' minutes.');
    }
}
