<?php

namespace App\Http\Resources\Admin;

use App\Enums\UnitStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Spec § 6 property summary — no street address, ownership, utilities,
 * documents or prices; city + state only. Load `units` first.
 */
class AdminPropertySummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        $units = $this->units;

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'location'      => [
                'city'  => $this->city,
                'state' => $this->state,
            ],
            'type'          => $this->type?->value,
            'unitsTotal'    => $units->count(),
            'unitsOccupied' => $units->where('status', UnitStatus::OCCUPIED)->count(),
            'createdAt'     => $this->created_at?->toISOString(),
        ];
    }
}
