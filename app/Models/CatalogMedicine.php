<?php

namespace App\Models;

use Database\Factories\CatalogMedicineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['generic_name', 'brand_names', 'category_id', 'form', 'strength', 'purpose', 'how_to_take', 'warnings', 'is_otc'])]
class CatalogMedicine extends Model
{
    /** @use HasFactory<CatalogMedicineFactory> */
    use HasFactory;

    protected $table = 'medicines_catalog';

    protected function casts(): array
    {
        return ['is_otc' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MedicineCategory::class, 'category_id');
    }

    /** Case-insensitive lookup by generic name, portable across SQLite and MySQL. */
    public static function findByName(string $name): ?self
    {
        return static::whereRaw('lower(generic_name) = ?', [mb_strtolower(trim($name))])->first();
    }
}
