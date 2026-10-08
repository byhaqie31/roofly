<?php

namespace App\Models;

use App\Enums\ReporterRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketComment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ticket_id',
        'author_id',
        'author_role',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'author_role' => ReporterRole::class,
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
