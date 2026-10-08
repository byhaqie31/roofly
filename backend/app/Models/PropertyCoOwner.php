<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyCoOwner extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'property_id',
        'user_id',
        'name',
        'share_pct',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'share_pct'  => 'decimal:2',
            'is_primary' => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
