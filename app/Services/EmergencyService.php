<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\DoseLog;
use App\Models\User;
use App\Notifications\CareAlertNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Emergency rule: when a patient misses several doses in a row, alert the
 * trusted contacts they chose and their linked doctors. One alert is raised per
 * streak; it closes by itself when the patient takes a dose again.
 */
class EmergencyService
{
    /** Missed doses in a row, counting back from the patient's most recent decided dose. */
    public function consecutiveMisses(User $patient): int
    {
        $recent = DoseLog::where('patient_id', $patient->id)
            ->whereIn('status', [DoseLog::TAKEN, DoseLog::MISSED])
            ->orderByDesc('scheduled_at')
            ->limit(100)
            ->pluck('status');

        $run = 0;
        foreach ($recent as $status) {
            if ($status !== DoseLog::MISSED) {
                break;
            }
            $run++;
        }

        return $run;
    }

    /** Raise an emergency alert if the streak has reached the threshold. Returns the alert, or null. */
    public function evaluate(User $patient): ?Alert
    {
        $misses = $this->consecutiveMisses($patient);

        if ($misses < (int) config('medimind.emergency.consecutive_misses')) {
            return null;
        }

        if (Alert::open()->where('patient_id', $patient->id)->where('type', Alert::EMERGENCY)->exists()) {
            return null;
        }

        $contacts = $patient->trustedContacts()->where('notify', true)->get();
        $doctors = $patient->activeDoctors();

        $alert = Alert::create([
            'patient_id' => $patient->id,
            'type' => Alert::EMERGENCY,
            'level' => 'high',
            'message' => $patient->name.' has missed '.$misses.' doses in a row.',
            'data' => ['misses' => $misses, 'contacts' => $contacts->count(), 'doctors' => $doctors->count()],
        ]);

        // Contacts get a short email with no health details. Doctors can open the record.
        foreach ($contacts as $contact) {
            Notification::route('mail', [$contact->email => $contact->name])->notify(new CareAlertNotification(
                'Please check on '.$patient->name,
                $patient->name.' has missed '.$misses.' medicine doses in a row. Please check how they are doing. This message was sent because they chose you as a trusted contact in MediMind.',
                null,
                ['mail'],
            ));
        }

        foreach ($doctors as $doctor) {
            $doctor->notify(new CareAlertNotification(
                'Emergency alert: '.$patient->name,
                $alert->message.' Please consider following up.',
                route('doctor.patients.show', $patient),
            ));
        }

        $patient->notify(new CareAlertNotification(
            'We told your contacts',
            'You have missed '.$misses.' doses in a row, so MediMind let '.$this->recipientsText($contacts->count(), $doctors->count()).' know. Taking your next dose will close the alert.',
            route('patient.dashboard'),
            ['database'],
        ));

        return $alert;
    }

    /** The patient took a dose, so the streak is over and open emergency alerts close. */
    public function resolveOnTaken(User $patient): void
    {
        Alert::open()->where('patient_id', $patient->id)->where('type', Alert::EMERGENCY)
            ->each(fn (Alert $alert) => $alert->resolve('resumed'));
    }

    private function recipientsText(int $contacts, int $doctors): string
    {
        $parts = array_filter([
            $contacts ? $contacts.' trusted contact'.($contacts > 1 ? 's' : '') : null,
            $doctors ? $doctors.' doctor'.($doctors > 1 ? 's' : '') : null,
        ]);

        return $parts ? implode(' and ', $parts) : 'no one (you have not added a trusted contact or doctor)';
    }
}
