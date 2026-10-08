<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'status'           => $this->status,
            'invitedAt'        => $this->invited_at?->toISOString(),
            'createdAt'        => $this->created_at?->toISOString(),
            'personal'         => $this->personal_info,
            'emergencyContact' => $this->emergency_contact,
        ];
    }
}
