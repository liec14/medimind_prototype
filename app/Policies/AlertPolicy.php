<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    /** A doctor can act on alerts of patients who accepted them. */
    public function manage(User $user, Alert $alert): bool
    {
        return $user->hasRole(Role::Doctor)
            && $user->isApproved()
            && $alert->patient->hasActiveDoctor($user);
    }
}
