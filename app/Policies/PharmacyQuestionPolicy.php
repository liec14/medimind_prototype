<?php

namespace App\Policies;

use App\Models\PharmacyQuestion;
use App\Models\User;

class PharmacyQuestionPolicy
{
    /** Only the pharmacy the question was sent to can answer it. */
    public function answer(User $user, PharmacyQuestion $question): bool
    {
        return $user->pharmacy !== null && $user->pharmacy->id === $question->pharmacy_id;
    }
}
