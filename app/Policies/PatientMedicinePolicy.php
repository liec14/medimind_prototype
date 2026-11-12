<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\PatientMedicine;
use App\Models\User;

class PatientMedicinePolicy
{
    /** The patient, or a doctor the patient has accepted, may view and correct a medicine entry. */
    public function update(User $user, PatientMedicine $medicine): bool
    {
        return $user->is($medicine->patient)
            || ($user->hasRole(Role::Doctor)
                && $user->isApproved()
                && $medicine->patient->hasActiveDoctor($user));
    }

    public function view(User $user, PatientMedicine $medicine): bool
    {
        return $this->update($user, $medicine);
    }

    /** Only the patient removes entries; doctors mark them inactive instead. */
    public function delete(User $user, PatientMedicine $medicine): bool
    {
        return $user->is($medicine->patient);
    }
}
