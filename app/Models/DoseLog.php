<?php

namespace App\Models;

use Database\Factories\DoseLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_medicine_id', 'patient_id', 'scheduled_at', 'status', 'confirmed_at', 'reminded_at', 'snoozed_until', 'snoozed_count'])]
class DoseLog extends Model
{
    /** @use HasFactory<DoseLogFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const TAKEN = 'taken';

    public const MISSED = 'missed';

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'reminded_at' => 'datetime',
            'snoozed_until' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(PatientMedicine::class, 'patient_medicine_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }
}
