<?php

namespace App\Models;

use Database\Factories\PatientMedicineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'dosage', 'purpose', 'category_id', 'start_date', 'end_date', 'supply_until', 'instructions', 'active'])]
class PatientMedicine extends Model
{
    /** @use HasFactory<PatientMedicineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'supply_until' => 'date',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(Schedule::class);
    }

    public function doseLogs(): HasMany
    {
        return $this->hasMany(DoseLog::class);
    }

    public function catalogMedicine(): BelongsTo
    {
        return $this->belongsTo(CatalogMedicine::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MedicineCategory::class, 'category_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
