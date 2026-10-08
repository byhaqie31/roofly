<?php
// backend/app/Http/Requests/Admin/StoreAdminRequest.php
namespace App\Http\Requests\Admin;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only a super-admin may mint another super-admin (spec § 5).
        return ! $this->boolean('isSuperAdmin') || (bool) $this->user()?->is_super_admin;
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users,email',
            'permissions'   => 'present|array',
            'permissions.*' => [Rule::in(AdminPermissions::keys())],
            'isSuperAdmin'  => 'sometimes|boolean',
        ];
    }
}
