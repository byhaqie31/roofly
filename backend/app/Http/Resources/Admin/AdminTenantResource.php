<?php

namespace App\Http\Resources\Admin;

use App\Enums\AgreementStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Spec § 6 tenant tier — identity + placement only. Load
 * `inviter:id,name` and `agreements.unit.property:id,name,owner_id` first.
 */
class AdminTenantResource extends JsonResource
{
    public function toArray($request): array
    {
        $agreement = $this->agreements
            ->sortByDesc(fn ($a) => ($a->status === AgreementStatus::ACTIVE ? 1 : 0) . $a->start_date)
            ->first();
        $property = $agreement?->unit?->property;

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'status'       => $this->status,
            'ownerId'      => $this->invited_by ?? $property?->owner_id,
            'ownerName'    => $this->inviter?->name ?? $property?->owner?->name,
            'propertyName' => $property?->name,
            'unitLabel'    => $agreement?->unit?->label,
            'invitedAt'    => $this->invited_at?->toISOString(),
            'acceptedAt'   => $this->first_login_at?->toISOString(),
            'createdAt'    => $this->created_at?->toISOString(),
        ];
    }
}
