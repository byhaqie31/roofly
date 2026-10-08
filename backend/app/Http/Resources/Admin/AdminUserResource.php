<?php
// backend/app/Http/Resources/Admin/AdminUserResource.php
namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'email'        => $this->email,
            'permissions'  => $this->getPermissionNames()->values()->all(),
            'isSuperAdmin' => (bool) $this->is_super_admin,
            'status'       => $this->isDisabled() ? 'disabled' : ($this->first_login_at === null ? 'invited' : 'active'),
            'lastActiveAt' => $this->last_active_at?->toISOString(),
            'createdAt'    => $this->created_at?->toISOString(),
        ];
    }
}
