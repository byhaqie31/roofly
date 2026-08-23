<?php

namespace App\Http\Resources\Admin;

use App\Enums\UnitStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/** Spec § 6 property summary — no ownership / utilities / documents / prices. Load `units` first. */
class AdminPropertySummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        $units = $this->units;

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'address'       => [
                'line'     => $this->address,
                'postcode' => $this->postcode,
                'city'     => $this->city,
                'state'    => $this->state,
            ],
            'type'          => $this->type?->value,
            'unitsTotal'    => $units->count(),
            'unitsOccupied' => $units->where('status', UnitStatus::OCCUPIED)->count(),
            'createdAt'     => $this->created_at?->toISOString(),
        ];
    }
}
