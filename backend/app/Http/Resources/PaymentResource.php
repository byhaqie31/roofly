<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'invoiceId' => $this->invoice_id,
            'amount'    => $this->amount_cents,
            'method'    => $this->method?->value,
            'status'    => $this->status?->value,
            'paidAt'    => $this->paid_at?->toISOString(),
            'reference' => $this->reference,
            'createdAt' => $this->created_at?->toISOString(),
            // Transfer claims (spec 2026-10-08 § 3.3)
            'payoutAccountId' => $this->payout_account_id,
            'note'            => $this->note,
            'rejectionReason' => $this->rejection_reason,
            'confirmedAt'     => $this->confirmed_at?->toISOString(),
        ];
    }
}
