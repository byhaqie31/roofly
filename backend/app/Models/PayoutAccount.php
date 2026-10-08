<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Where an owner gets paid (spec 2026-10-08 payout-accounts-duitnow § 3.1).
 * Many per owner, exactly one `is_default` once the owner has any.
 */
class PayoutAccount extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'owner_id',
        'label',
        'bank',
        'account_holder_name',
        'account_number',
        'duitnow_id_type',
        'duitnow_id',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    /** Digits only — owners paste "5140 1234 4521" or "5140-1234-4521". */
    public static function normaliseAccountNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $value);

        return $digits === '' ? null : $digits;
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class, 'payout_account_id');
    }
}
