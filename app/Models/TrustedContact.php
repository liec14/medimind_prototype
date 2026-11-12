<?php

namespace App\Models;

use Database\Factories\TrustedContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'email', 'relationship', 'notify'])]
class TrustedContact extends Model
{
    /** @use HasFactory<TrustedContactFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['notify' => 'boolean'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }
}
