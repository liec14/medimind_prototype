<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['wake_time', 'breakfast_time', 'lunch_time', 'dinner_time', 'sleep_time'])]
class PatientRoutine extends Model
{
    public const DEFAULTS = [
        'wake_time' => '07:00',
        'breakfast_time' => '08:00',
        'lunch_time' => '12:00',
        'dinner_time' => '18:30',
        'sleep_time' => '22:00',
    ];

    public const LABELS = [
        'wake_time' => 'Wake up',
        'breakfast_time' => 'Breakfast',
        'lunch_time' => 'Lunch',
        'dinner_time' => 'Dinner',
        'sleep_time' => 'Bedtime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /** An unsaved routine with default times, for patients who have not set one. */
    public static function defaults(): self
    {
        return new self(self::DEFAULTS);
    }
}
