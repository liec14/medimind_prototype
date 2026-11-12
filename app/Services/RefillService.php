<?php

namespace App\Services;

use App\Models\PatientMedicine;
use App\Models\Pharmacy;
use App\Models\PharmacyFollowup;
use App\Models\User;
use App\Notifications\CareAlertNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Refill reminders for pharmacies. A pharmacy only ever sees patients who chose
 * to share with it, and only the medicine name and the date the supply runs out.
 * It never sees dosage, schedule, adherence, risk, or the patient's contact
 * details: follow-ups go out through MediMind.
 */
class RefillService
{
    /**
     * Medicines of consenting patients whose supply runs out within the window, or already has.
     * A medicine whose course ends before the supply does needs no refill.
     *
     * @return Collection<int, array{medicine: PatientMedicine, patient: User, due_on: CarbonImmutable, days_left: int, status: string, last_followup: ?CarbonImmutable}>
     */
    public function dueFor(Pharmacy $pharmacy): Collection
    {
        $today = CarbonImmutable::now()->startOfDay();
        $window = $today->addDays((int) config('medimind.refill.due_days'));
        $stoppedAfter = (int) config('medimind.refill.stopped_after_days');

        $medicines = PatientMedicine::query()
            ->active()
            ->whereNotNull('supply_until')
            ->where('supply_until', '<=', $window->toDateString())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereColumn('supply_until', '<', 'end_date'))
            ->whereHas('patient.consentedPharmacies', fn ($q) => $q->where('pharmacies.id', $pharmacy->id))
            ->with('patient')
            ->orderBy('supply_until')
            ->get();

        $lastFollowups = PharmacyFollowup::where('pharmacy_id', $pharmacy->id)
            ->whereIn('patient_medicine_id', $medicines->pluck('id'))
            ->get()->groupBy('patient_medicine_id')
            ->map(fn ($rows) => CarbonImmutable::instance($rows->max('sent_at')));

        return $medicines->map(function (PatientMedicine $medicine) use ($today, $stoppedAfter, $lastFollowups) {
            $dueOn = CarbonImmutable::instance($medicine->supply_until)->startOfDay();
            $daysLeft = (int) $today->diffInDays($dueOn, false);

            return [
                'medicine' => $medicine,
                'patient' => $medicine->patient,
                'due_on' => $dueOn,
                'days_left' => $daysLeft,
                'status' => match (true) {
                    $daysLeft < -$stoppedAfter => 'stopped',
                    $daysLeft < 0 => 'overdue',
                    default => 'due_soon',
                },
                'last_followup' => $lastFollowups[$medicine->id] ?? null,
            ];
        })->values();
    }

    public function isDue(Pharmacy $pharmacy, PatientMedicine $medicine): bool
    {
        return $this->dueFor($pharmacy)->contains(fn ($row) => $row['medicine']->is($medicine));
    }

    /** The message the pharmacist can send as is or edit. */
    public function defaultMessage(Pharmacy $pharmacy, PatientMedicine $medicine): string
    {
        $first = explode(' ', trim($medicine->patient->name))[0];

        return "Hello {$first}, your {$medicine->name} may be running low. You can come by {$pharmacy->name} for a refill, or ask us if you have any questions about taking it safely.";
    }

    /** Send a follow-up through MediMind (in-app and email). Once a day per patient and medicine. */
    public function sendFollowUp(Pharmacy $pharmacy, PatientMedicine $medicine, string $message): PharmacyFollowup
    {
        $cooldown = (int) config('medimind.refill.followup_cooldown_hours');

        $recent = PharmacyFollowup::where('pharmacy_id', $pharmacy->id)
            ->where('patient_medicine_id', $medicine->id)
            ->where('sent_at', '>', now()->subHours($cooldown))
            ->exists();

        if ($recent) {
            throw ValidationException::withMessages([
                'message' => 'You already messaged this patient about this medicine in the last '.$cooldown.' hours.',
            ]);
        }

        $followup = PharmacyFollowup::create([
            'pharmacy_id' => $pharmacy->id,
            'patient_id' => $medicine->patient_id,
            'patient_medicine_id' => $medicine->id,
            'message' => $message,
            'sent_at' => now(),
        ]);

        $medicine->patient->notify(new CareAlertNotification(
            'A message from '.$pharmacy->name,
            $message,
            route('patient.dashboard'),
        ));

        return $followup;
    }
}
