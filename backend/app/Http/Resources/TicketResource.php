<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'unitId'       => $this->unit_id,
            'reporterId'   => $this->reporter_id,
            'reporterRole' => $this->reporter_role?->value,
            'category'     => $this->category?->value,
            'priority'     => $this->priority?->value,
            'title'        => $this->title,
            'description'  => $this->description,
            'status'       => $this->status?->value,
            'createdAt'    => $this->created_at?->toISOString(),
            'updatedAt'    => $this->updated_at?->toISOString(),
            'resolvedAt'   => $this->resolved_at?->toISOString(),
        ];
    }
}
