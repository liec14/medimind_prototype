<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\ReminderSuggestion;
use App\Services\ReminderAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ReminderSuggestionController extends Controller
{
    public function __construct(private ReminderAdjustmentService $adjustments) {}

    public function accept(ReminderSuggestion $suggestion): RedirectResponse
    {
        Gate::authorize('update', $suggestion);
        abort_unless($suggestion->status === ReminderSuggestion::PENDING, 404);

        $this->adjustments->accept($suggestion);

        return back()->with('status', 'Done. Your '.$suggestion->slot.' reminders for '.$suggestion->medicine->name.' now come '.$suggestion->shift_minutes.' minutes earlier.');
    }

    public function dismiss(ReminderSuggestion $suggestion): RedirectResponse
    {
        Gate::authorize('update', $suggestion);
        abort_unless($suggestion->status === ReminderSuggestion::PENDING, 404);

        $this->adjustments->dismiss($suggestion);

        return back()->with('status', 'OK, your reminder times stay the same.');
    }
}
