<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['medicine_a_id', 'medicine_b_id', 'severity', 'note'])]
class DrugInteraction extends Model
{
    public const HIGH = 'high';

    public const MODERATE = 'moderate';

    public function medicineA(): BelongsTo
    {
        return $this->belongsTo(CatalogMedicine::class, 'medicine_a_id');
    }

    public function medicineB(): BelongsTo
    {
        return $this->belongsTo(CatalogMedicine::class, 'medicine_b_id');
    }
}
