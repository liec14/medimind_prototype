<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'assessed_on', 'score', 'level', 'factors', 'computed_at'])]
class RiskAssessment extends Model
{
    protected function casts(): array
    {
        return [
            // Stored as plain Y-m-d so one-row-per-day lookups match exactly.
            'assessed_on' => 'date:Y-m-d',
            'score' => 'float',
            'factors' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function hasEnoughData(): bool
    {
        return (bool) ($this->factors['sufficient_data'] ?? false);
    }
}
