<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'propertyId' => $this->property_id,
            'label'      => $this->label,
            'bedrooms'   => $this->bedrooms,
            'bathrooms'  => $this->bathrooms,
            'sqft'       => $this->sqft,
            'status'     => $this->status?->value,
            'createdAt'  => $this->created_at?->toISOString(),
        ];
    }
}
