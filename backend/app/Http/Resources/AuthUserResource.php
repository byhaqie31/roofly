<?php

namespace App\Http\Resources;

use App\Support\AdminPermissions;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    public function toArray($request): array
    {
        $isAdmin = $this->role?->value === 'admin';
        $permissions = ! $isAdmin
            ? []
            : ($this->is_super_admin
                ? AdminPermissions::keys()
                : $this->getPermissionNames()->values()->all());

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'role'         => $this->role?->value,
            'permissions'  => $permissions,
            'isSuperAdmin' => (bool) $this->is_super_admin,
        ];
    }
}
