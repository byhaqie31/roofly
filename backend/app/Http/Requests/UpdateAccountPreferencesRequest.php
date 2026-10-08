<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale'      => 'sometimes|in:en,ms',
            'theme'       => 'sometimes|in:light,dark,system',
            'moneyLocale' => 'sometimes|in:en-MY',
        ];
    }
}
