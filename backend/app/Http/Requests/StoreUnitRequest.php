<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:owner middleware gates the route
    }

    public function rules(): array
    {
        return [
            'label'     => 'required|string|max:255',
            'bedrooms'  => 'nullable|integer|min:0|max:20',
            'bathrooms' => 'nullable|integer|min:0|max:20',
            'sqft'      => 'nullable|integer|min:1',
            'status'    => 'nullable|in:vacant,occupied,maintenance',
        ];
    }

    /** Column-keyed payload for Unit::create(). */
    public function toModelAttributes(): array
    {
        return $this->validated(); // keys are identical in both casings
    }
}
