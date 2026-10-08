<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

/** Key set pinned by AdminEnquiriesTest. Expects `handler` loaded. */
class AdminEnquiryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'type'            => $this->type,
            'status'          => $this->status,
            'message'         => $this->message,
            'pageUrl'         => $this->page_url,
            'pageLabel'       => $this->page_label,
            'name'            => $this->name,
            'email'           => $this->email,
            'role'            => $this->role,
            'userId'          => $this->user_id,
            'adminNote'       => $this->admin_note,
            'handledByName'   => $this->handler?->name,
            'statusChangedAt' => $this->status_changed_at?->toISOString(),
            'createdAt'       => $this->created_at?->toISOString(),
        ];
    }
}
