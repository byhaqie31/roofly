<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Envelope matching frontend InvoiceWithRefs. */
class InvoiceWithRefsResource extends JsonResource
{
    public function toArray($request): array
    {
        $agreement = $this->agreement;
        $unit      = $agreement?->unit;
        $property  = $unit?->property;

        return [
            'invoice'   => new InvoiceResource($this->resource),
            'agreement' => $agreement ? new AgreementResource($agreement) : null,
            'unit'      => $unit ? new UnitResource($unit) : null,
            'property'  => $property ? new PropertyResource($property) : null,
            'tenant'    => $agreement?->tenant ? new TenantResource($agreement->tenant) : null,
            'payments'  => PaymentResource::collection($this->payments),
            // Resolved for the invoice's agreement (spec 2026-10-08 § 4)
            'payoutAccount' => ($account = $agreement?->resolvedPayoutAccount()) ? new PayoutAccountResource($account) : null,
        ];
    }
}
