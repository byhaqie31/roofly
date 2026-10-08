<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketCommentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'ticketId'   => $this->ticket_id,
            'authorId'   => $this->author_id,
            'authorRole' => $this->author_role?->value,
            'body'       => $this->body,
            'createdAt'  => $this->created_at?->toISOString(),
        ];
    }
}
