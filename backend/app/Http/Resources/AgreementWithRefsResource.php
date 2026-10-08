<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Envelope matching frontend AgreementWithRefs: {agreement, unit, property, tenant}. */
class AgreementWithRefsResource extends JsonResource
{
    public function toArray($request): array
    {
        $unit     = $this->unit;
        $property = $unit?->property;

        return [
            'agreement' => new AgreementResource($this->resource),
            'unit'      => $unit ? new UnitResource($unit) : null,
            'property'  => $property ? new PropertyResource($property) : null,
            'tenant'    => $this->tenant ? new TenantResource($this->tenant) : null,
            // Resolved: own account ?? owner's default ?? null (spec 2026-10-08 § 4)
            'payoutAccount' => ($account = $this->resolvedPayoutAccount()) ? new PayoutAccountResource($account) : null,
        ];
    }
}
