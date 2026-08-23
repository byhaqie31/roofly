<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

/** Key set pinned by AdminLeadsTest. Expects `page_views_count` and `demo_entered` attributes set by the controller, and `convertedUser` loaded. */
class AdminLeadResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'email'              => $this->email,
            'source'             => $this->source,
            'firstSeenAt'        => $this->first_seen_at?->toISOString(),
            'lastSeenAt'         => $this->last_seen_at?->toISOString(),
            'pageViews'          => (int) ($this->page_views_count ?? 0),
            'demoEntered'        => (bool) ($this->demo_entered ?? false),
            'convertedUserId'    => $this->converted_user_id,
            'convertedOwnerName' => $this->convertedUser?->name,
        ];
    }
}
