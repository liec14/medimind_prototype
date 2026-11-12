<?php

namespace App\Models;

use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['frequency', 'times', 'days_of_week'])]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    /** Preset frequencies: label and the number of doses per day (null = any number). */
    public const FREQUENCIES = [
        'once_daily' => ['label' => 'Once a day', 'count' => 1],
        'twice_daily' => ['label' => 'Twice a day', 'count' => 2],
        'three_times_daily' => ['label' => 'Three times a day', 'count' => 3],
        'four_times_daily' => ['label' => 'Four times a day', 'count' => 4],
        'custom' => ['label' => 'Custom times', 'count' => null],
    ];

    public const DAY_NAMES = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];

    protected function casts(): array
    {
        return ['times' => 'array', 'days_of_week' => 'array'];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(PatientMedicine::class, 'patient_medicine_id');
    }

    public function runsOn(int $dayOfWeek): bool
    {
        return empty($this->days_of_week) || in_array($dayOfWeek, $this->days_of_week, true);
    }

    /** Human summary such as "Twice a day · 8:00 AM, 8:00 PM". */
    public function summary(): string
    {
        $times = collect($this->times)->map(fn ($t) => date('g:i A', strtotime($t)))->implode(', ');
        $days = empty($this->days_of_week)
            ? ''
            : ' · '.collect($this->days_of_week)->map(fn ($d) => self::DAY_NAMES[$d])->implode(', ');

        return self::FREQUENCIES[$this->frequency]['label'].' · '.$times.$days;
    }
}
