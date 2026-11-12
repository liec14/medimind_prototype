<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockRequest extends FormRequest
{
    /** Role and ownership are enforced by route middleware and PharmacyStockPolicy. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catalog_medicine_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'exists:medicines_catalog,id'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'in_stock' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // An unchecked checkbox is not submitted at all.
        $this->merge(['in_stock' => $this->boolean('in_stock')]);
    }
}
