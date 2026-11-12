<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pharmacy_id', 'patient_id', 'patient_medicine_id', 'message', 'sent_at'])]
class PharmacyFollowup extends Model
{
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(PatientMedicine::class, 'patient_medicine_id');
    }
}
