<?php

namespace App\Policies;

use App\Models\PharmacyStock;
use App\Models\User;

class PharmacyStockPolicy
{
    /** A pharmacy manages only its own price list. */
    public function update(User $user, PharmacyStock $stock): bool
    {
        return $user->pharmacy !== null && $user->pharmacy->id === $stock->pharmacy_id;
    }

    public function delete(User $user, PharmacyStock $stock): bool
    {
        return $this->update($user, $stock);
    }
}
