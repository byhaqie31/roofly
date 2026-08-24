<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
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

        $isOwner = $this->role === UserRole::OWNER;

        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'email'                => $this->email,
            'phone'                => $this->phone,
            'role'                 => $this->role?->value,
            'permissions'          => $permissions,
            'isSuperAdmin'         => (bool) $this->is_super_admin,
            'hasPassword'          => $this->password !== null,
            'avatarUrl'            => $this->avatar_url,
            'onboardedAt'          => $isOwner ? $this->onboarded_at?->toISOString() : null,
            'purposes'             => $isOwner ? ($this->purposes ?? []) : [],
            'checklistDismissedAt' => $isOwner ? $this->checklist_dismissed_at?->toISOString() : null,
        ];
    }
}
