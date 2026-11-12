<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['symptom', 'keywords', 'catalog_medicine_id', 'priority', 'notes', 'see_doctor_if'])]
class OtcRule extends Model
{
    public function catalogMedicine(): BelongsTo
    {
        return $this->belongsTo(CatalogMedicine::class);
    }

    /** True if any search term appears in the symptom label or its keywords. */
    public function matchesAny(array $terms): bool
    {
        $haystack = mb_strtolower($this->symptom.','.$this->keywords);

        foreach ($terms as $term) {
            if ($term !== '' && str_contains($haystack, $term)) {
                return true;
            }
        }

        return false;
    }
}
