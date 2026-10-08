<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:owner middleware gates the route
    }

    public function rules(): array
    {
        return [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:30',
        ];
    }
}
