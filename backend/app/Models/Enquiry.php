<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message from the in-app help button. Name/email/role are copied at send time so the row survives account changes. */
class Enquiry extends Model
{
    use HasFactory, HasUuids;

    public const TYPES = ['issue', 'feedback', 'question'];
    public const STATUSES = ['new', 'replied', 'closed'];

    protected $fillable = [
        'user_id', 'name', 'email', 'role', 'type', 'message', 'page_url', 'page_label', 'user_agent',
        'status', 'admin_note', 'handled_by', 'status_changed_at',
    ];

    protected function casts(): array
    {
        return ['status_changed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
