<?php
// backend/app/Http/Resources/Admin/AuditEntryResource.php
namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** One admin audit row (Spatie Activity with log_name = admin). Load `causer` and `subject`. */
class AuditEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        $props = $this->properties ?? collect();

        return [
            'id'          => (string) $this->id,
            'action'      => $this->event ?? $this->description,
            'actorId'     => $this->causer_id,
            'actorName'   => $this->causer?->name,
            'subjectType' => $this->subject_type ? Str::lower(class_basename($this->subject_type)) : null,
            'subjectId'   => $this->subject_id,
            'subjectName' => $this->subject instanceof User ? $this->subject->name : null,
            'before'      => (object) ($props['before'] ?? []),
            'after'       => (object) ($props['after'] ?? []),
            'reason'      => $props['reason'] ?? null,
            'ip'          => $props['ip'] ?? null,
            'createdAt'   => $this->created_at?->toISOString(),
        ];
    }
}
