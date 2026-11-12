<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PatientMedicine;
use App\Models\Schedule;
use App\Services\ScheduleService;
use Illuminate\Http\Request;

trait BuildsScheduleForm
{
    /**
     * Values for the schedule form. Picking a different frequency (?frequency=)
     * pre-fills times from the patient's routine; otherwise the saved times show.
     */
    protected function scheduleFormData(Request $request, PatientMedicine $medicine, ScheduleService $service): array
    {
        $saved = $medicine->schedule;
        $frequency = $request->query('frequency', $saved?->frequency ?? 'once_daily');
        if (! isset(Schedule::FREQUENCIES[$frequency])) {
            $frequency = 'once_daily';
        }

        $keepSaved = $saved && $saved->frequency === $frequency;
        $times = $keepSaved
            ? $saved->times
            : $service->suggestTimes($frequency, $medicine->patient->routineOrDefault());

        return [
            'medicine' => $medicine,
            'schedule' => $saved,
            'frequency' => $frequency,
            'times' => array_pad($times, 6, ''),
            'days' => $saved?->days_of_week ?? [],
        ];
    }
}
