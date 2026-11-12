<?php

namespace App\Policies;

use App\Models\ReminderSuggestion;
use App\Models\User;

class ReminderSuggestionPolicy
{
    /** Only the patient decides whether their reminder times change. */
    public function update(User $user, ReminderSuggestion $suggestion): bool
    {
        return $user->id === $suggestion->medicine->patient_id;
    }
}
