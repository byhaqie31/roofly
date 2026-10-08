<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'agreementId'   => $this->agreement_id,
            'invoiceNumber' => $this->invoice_number,
            'amount'        => $this->amount_cents,
            'lateFee'       => $this->late_fee_cents,
            'dueDate'       => $this->due_date->format('Y-m-d'),
            'status'        => $this->status?->value,
            'createdAt'     => $this->created_at?->toISOString(),
        ];
    }
}
