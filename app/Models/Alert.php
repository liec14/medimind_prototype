<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'type', 'level', 'message', 'data'])]
class Alert extends Model
{
    public const RISK = 'risk';

    public const EMERGENCY = 'emergency';

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolve(string $resolution): void
    {
        $this->forceFill(['resolved_at' => now(), 'resolution' => $resolution])->save();
    }
}
