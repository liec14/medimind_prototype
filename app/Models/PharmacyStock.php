<?php

namespace App\Models;

use Database\Factories\PharmacyStockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['catalog_medicine_id', 'price', 'in_stock'])]
class PharmacyStock extends Model
{
    /** @use HasFactory<PharmacyStockFactory> */
    use HasFactory;

    protected $table = 'pharmacy_stock';

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'in_stock' => 'boolean'];
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function catalogMedicine(): BelongsTo
    {
        return $this->belongsTo(CatalogMedicine::class);
    }

    /** Prices not confirmed for a month are shown with a warning. */
    public function isStale(): bool
    {
        return $this->updated_at->lt(now()->subDays(30));
    }
}
