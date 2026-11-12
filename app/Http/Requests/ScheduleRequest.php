<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ScheduleRequest extends FormRequest
{
    /** Access is enforced by PatientMedicinePolicy in the controllers. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'frequency' => ['required', Rule::in(array_keys(Schedule::FREQUENCIES))],
            'times' => ['required', 'array', 'max:8'],
            'times.*' => ['nullable', 'date_format:H:i'],
            'days' => ['nullable', 'array'],
            'days.*' => ['integer', 'between:0,6'],
        ];
    }

    /** The number of filled-in times must match the chosen frequency. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $frequency = $this->input('frequency');
            if (! isset(Schedule::FREQUENCIES[$frequency]) || $validator->errors()->has('times.*')) {
                return;
            }

            $times = collect($this->input('times', []))->filter()->unique();
            $expected = Schedule::FREQUENCIES[$frequency]['count'];

            if ($times->isEmpty()) {
                $validator->errors()->add('times', 'Add at least one time.');
            } elseif ($expected !== null && $times->count() !== $expected) {
                $validator->errors()->add('times', 'Choose exactly '.$expected.' different time'.($expected > 1 ? 's' : '').' for "'.Schedule::FREQUENCIES[$frequency]['label'].'".');
            }
        }];
    }
}
