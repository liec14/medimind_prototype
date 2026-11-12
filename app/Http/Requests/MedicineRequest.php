<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicineRequest extends FormRequest
{
    /** Access is enforced by PatientMedicinePolicy in the controllers. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'dosage' => ['required', 'string', 'max:100'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:medicine_categories,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'supply_until' => ['nullable', 'date', 'after_or_equal:start_date'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // An unchecked checkbox is not submitted at all.
        $this->merge(['active' => $this->boolean('active')]);
    }
}
