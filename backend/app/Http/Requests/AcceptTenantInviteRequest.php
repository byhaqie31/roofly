<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptTenantInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guest route; the token is the authorisation
    }

    public function rules(): array
    {
        return [
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
