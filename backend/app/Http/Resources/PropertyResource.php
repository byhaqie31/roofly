<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'ownerId'       => $this->owner_id,
            'name'          => $this->name,
            'internalLabel' => $this->internal_label,
            'type'          => $this->type?->value,
            'purpose'       => $this->purpose?->value ?? 'rental',
            'notes'         => $this->notes,
            'address'       => $this->address,
            'city'          => $this->city,
            'state'         => $this->state,
            'postcode'      => $this->postcode,
            'yearBuilt'     => $this->year_built,
            'builtUpSqft'   => $this->built_up_sqft,
            'landSqft'      => $this->land_sqft,
            'bedrooms'      => $this->bedrooms,
            'bathrooms'     => $this->bathrooms,
            'parkingLots'   => $this->parking_lots,
            'furnishing'    => $this->furnishing?->value,
            'ownership'     => $this->ownership,
            'utilities'     => $this->utilities,
            'coOwners'      => PropertyCoOwnerResource::collection($this->coOwners),
            'createdAt'     => $this->created_at?->toISOString(),
        ];
    }
}
