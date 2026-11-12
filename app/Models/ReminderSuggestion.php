<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_medicine_id', 'slot', 'shift_minutes', 'status'])]
class ReminderSuggestion extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DISMISSED = 'dismissed';

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(PatientMedicine::class, 'patient_medicine_id');
    }
}
