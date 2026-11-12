<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'address', 'contact'])]
class Pharmacy extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stock(): HasMany
    {
        return $this->hasMany(PharmacyStock::class);
    }

    public function followups(): HasMany
    {
        return $this->hasMany(PharmacyFollowup::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(PharmacyQuestion::class);
    }

    /** Patients who agreed to share limited medication info with this pharmacy. */
    public function consentingPatients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pharmacy_patient_consents', 'pharmacy_id', 'patient_id')
            ->withPivot('granted_at');
    }
}
