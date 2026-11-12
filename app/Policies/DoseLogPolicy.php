<?php

namespace App\Policies;

use App\Models\DoseLog;
use App\Models\User;

class DoseLogPolicy
{
    /** Only the patient records their own intake. Doctors can read history but never alter it. */
    public function update(User $user, DoseLog $dose): bool
    {
        return $user->id === $dose->patient_id;
    }
}
