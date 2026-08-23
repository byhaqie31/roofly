<?php

namespace App\Http\Resources\Admin;

use App\Support\OwnerCounts;
use App\Support\PlanCaps;
use Illuminate\Http\Resources\Json\JsonResource;

/** Spec § 6 owner tier. Key set pinned by AdminResourcesTest — do not add keys casually. */
class AdminOwnerResource extends JsonResource
{
    public function toArray($request): array
    {
        $counts = OwnerCounts::for($this->resource);

        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'businessName'     => $this->business_name,
            'planTier'         => $this->plan_tier?->value ?? 'free',
            'unitsUsed'        => $counts['units'],
            'unitsCap'         => PlanCaps::unitsCap($this->plan_tier),
            'status'           => $this->isSuspended() ? 'suspended' : 'active',
            'suspendedAt'      => $this->suspended_at?->toISOString(),
            'suspensionReason' => $this->suspension_reason,
            'createdAt'        => $this->created_at?->toISOString(),
            'lastActiveAt'     => $this->last_active_at?->toISOString(),
            'counts'           => $counts,
        ];
    }
}
