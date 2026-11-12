<?php

namespace App\Services;

use App\Models\CatalogMedicine;
use App\Models\PatientMedicine;
use App\Models\User;

class MedicineService
{
    public function __construct(private ScheduleService $schedules) {}

    /**
     * Create or update a patient's medicine from validated form data.
     *
     * If the typed name matches a catalog medicine, link it and fall back to the
     * catalog's category and purpose where the user left them blank.
     */
    public function save(PatientMedicine $medicine, array $data, User $actor, ?User $patient = null): PatientMedicine
    {
        $catalog = CatalogMedicine::findByName($data['name']);

        $medicine->fill($data);
        $medicine->active = (bool) ($data['active'] ?? false);
        $medicine->catalog_medicine_id = $catalog?->id;

        if ($catalog) {
            $medicine->name = $catalog->generic_name;
            $medicine->category_id ??= $catalog->category_id;
            $medicine->purpose = $medicine->purpose ?: $catalog->purpose;
        }

        if ($medicine->exists) {
            $medicine->updated_by = $actor->id;
        } else {
            $medicine->patient_id = $patient?->id ?? $actor->id;
            $medicine->entered_by = $actor->id;
        }

        $medicine->save();

        // Stopping a medicine or changing its dates changes which doses are due.
        $this->schedules->sync($medicine);

        return $medicine;
    }
}
