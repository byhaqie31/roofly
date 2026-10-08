<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tenancy extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'agreement_id',
        'tenant_id',
        'moved_in_at',
        'moved_out_at',
    ];

    protected function casts(): array
    {
        return [
            'moved_in_at'  => 'datetime',
            'moved_out_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'agreement_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
