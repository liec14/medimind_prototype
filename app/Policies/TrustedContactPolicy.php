<?php

namespace App\Policies;

use App\Models\TrustedContact;
use App\Models\User;

class TrustedContactPolicy
{
    public function update(User $user, TrustedContact $contact): bool
    {
        return $user->id === $contact->patient_id;
    }

    public function delete(User $user, TrustedContact $contact): bool
    {
        return $this->update($user, $contact);
    }
}
