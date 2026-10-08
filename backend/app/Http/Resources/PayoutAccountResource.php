<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frontend PayoutAccount (spec 2026-10-08 § 4). Full details — only ever sent
 * to the owning landlord, or to a tenant for the account resolved on their own
 * agreement. Admin never gets this resource.
 */
class PayoutAccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'label'             => $this->label,
            'bank'              => $this->bank,
            'accountHolderName' => $this->account_holder_name,
            'accountNumber'     => $this->account_number,
            'duitnowIdType'     => $this->duitnow_id_type,
            'duitnowId'         => $this->duitnow_id,
            'isDefault'         => (bool) $this->is_default,
            // withCount('agreements') on owner endpoints; 0 everywhere else.
            'agreementCount'    => (int) ($this->agreements_count ?? 0),
            'createdAt'         => $this->created_at?->toISOString(),
        ];
    }
}
