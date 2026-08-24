<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:owner middleware gates the route
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'type'     => 'required|in:condo,landed,shoplot,room',
            'purpose'  => ['sometimes', Rule::in(\App\Enums\PropertyPurpose::values())],
            'address'  => 'required|string|max:500',
            'city'     => 'required|string|max:100',
            'state'    => ['required', Rule::in(self::MY_STATES)],
            'postcode' => 'required|digits:5',
        ];
    }

    public const MY_STATES = [
        'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan',
        'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah',
        'Sarawak', 'Selangor', 'Terengganu',
        'W.P. Kuala Lumpur', 'W.P. Labuan', 'W.P. Putrajaya',
    ];

    /** Column-keyed payload for Property::create(). */
    public function toModelAttributes(): array
    {
        return $this->validated(); // Tier-1 keys are identical in both casings
    }
}
