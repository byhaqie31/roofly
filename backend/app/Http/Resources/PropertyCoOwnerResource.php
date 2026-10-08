<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PropertyCoOwnerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'sharePct'  => (float) $this->share_pct,
            'isPrimary' => (bool) $this->is_primary,
        ];
    }
}
