<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadEventResource extends JsonResource
{
    /** Email of the lead these events belong to, set via forLead() before resolving. */
    private static ?string $leadEmail = null;

    /** Scope the redaction below to a single lead's email before resolving a collection/instance. */
    public static function forLead(?string $email): void
    {
        static::$leadEmail = $email;
    }

    public function toArray($request): array
    {
        $props = (array) ($this->props ?? []);

        // props.email can be any email typed into a form on this visitor's session (e.g. a
        // mistyped waitlist signup) — redact it to the lead's own email so we never surface a
        // stranger's address through this endpoint.
        if (static::$leadEmail !== null && isset($props['email']) && $props['email'] !== static::$leadEmail) {
            $props['email'] = static::$leadEmail;
        }

        return [
            'id'        => $this->id,
            'event'     => $this->event,
            'path'      => $this->path,
            'props'     => (object) $props,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
