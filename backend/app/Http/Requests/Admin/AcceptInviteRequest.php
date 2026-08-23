<?php
// backend/app/Http/Requests/Admin/AcceptInviteRequest.php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guest route; the token is the authorisation
    }

    public function rules(): array
    {
        return [
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
