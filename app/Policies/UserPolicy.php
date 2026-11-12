<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Privacy rules for patient records (Data Privacy Act of 2012: health data is
 * sensitive personal information). A record is visible only to the patient,
 * and to doctors the patient has accepted. Pharmacies get a separate, narrower
 * ability and only where the patient consented.
 */
class UserPolicy
{
    public function viewRecord(User $viewer, User $patient): bool
    {
        if ($patient->role !== Role::Patient) {
            return false;
        }

        if ($viewer->is($patient)) {
            return true;
        }

        return $viewer->hasRole(Role::Doctor)
            && $viewer->isApproved()
            && $patient->hasActiveDoctor($viewer);
    }

    /** Limited info (e.g. refill dates) a pharmacy may see; never the full record. */
    public function viewShared(User $viewer, User $patient): bool
    {
        return $patient->role === Role::Patient
            && $viewer->hasRole(Role::Pharmacy)
            && $viewer->isApproved()
            && $viewer->pharmacy !== null
            && $patient->consentedPharmacies()->where('pharmacies.id', $viewer->pharmacy->id)->exists();
    }
}
