<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:owner middleware gates the route
    }

    public function rules(): array
    {
        return [
            'label'     => 'sometimes|string|max:255',
            'bedrooms'  => 'sometimes|nullable|integer|min:0|max:20',
            'bathrooms' => 'sometimes|nullable|integer|min:0|max:20',
            'sqft'      => 'sometimes|nullable|integer|min:1',
            'status'    => 'sometimes|in:vacant,occupied,maintenance',
        ];
    }

    /** Column-keyed payload for Unit::update(). */
    public function toModelAttributes(): array
    {
        return $this->validated(); // keys are identical in both casings
    }
}
