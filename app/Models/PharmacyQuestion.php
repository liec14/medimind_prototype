<?php

namespace App\Models;

use Database\Factories\PharmacyQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pharmacy_id', 'patient_id', 'patient_medicine_id', 'question'])]
class PharmacyQuestion extends Model
{
    /** @use HasFactory<PharmacyQuestionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
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
