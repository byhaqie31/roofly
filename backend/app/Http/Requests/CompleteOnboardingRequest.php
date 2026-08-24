<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteOnboardingRequest extends FormRequest
{
    public const PURPOSES = ['rental', 'own_stay', 'investment'];

    public function authorize(): bool
    {
        return true; // role:owner middleware gates the route
    }

    public function rules(): array
    {
        return [
            'purposes'   => 'required|array|min:1',
            'purposes.*' => 'in:' . implode(',', self::PURPOSES),
        ];
    }
}
