<?php
// backend/app/Http/Requests/Admin/UpdateAdminRequest.php
namespace App\Http\Requests\Admin;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->has('isSuperAdmin') || (bool) $this->user()?->is_super_admin;
    }

    public function rules(): array
    {
        return [
            'permissions'   => 'sometimes|array',
            'permissions.*' => [Rule::in(AdminPermissions::keys())],
            'isSuperAdmin'  => 'sometimes|boolean',
            'disabled'      => 'sometimes|boolean',
        ];
    }
}
