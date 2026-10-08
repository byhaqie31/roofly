<?php

namespace App\Http\Resources;

use App\Enums\ReporterRole;
use Illuminate\Http\Resources\Json\JsonResource;

/** Envelope matching frontend TicketWithRefs. Reporter is null for owner-reported tickets. */
class TicketWithRefsResource extends JsonResource
{
    public function toArray($request): array
    {
        $unit     = $this->unit;
        $property = $unit?->property;
        $isTenantReporter = $this->reporter_role === ReporterRole::TENANT;

        return [
            'ticket'   => new TicketResource($this->resource),
            'unit'     => $unit ? new UnitResource($unit) : null,
            'property' => $property ? new PropertyResource($property) : null,
            'reporter' => $isTenantReporter && $this->reporter ? new TenantResource($this->reporter) : null,
            'comments' => TicketCommentResource::collection(
                $this->comments->sortBy('created_at')->values()
            ),
        ];
    }
}
