<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'invoice_id',
        'amount_cents',
        'method',
        'billplz_bill_id',
        'reference',
        'status',
        'paid_at',
        'payout_account_id',
        'note',
        'rejection_reason',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'method'  => PaymentMethod::class,
            'status'  => PaymentStatus::class,
            'paid_at'      => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /** The account resolved when the tenant claimed the transfer (spec 2026-10-08 § 3.3). */
    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class, 'payout_account_id');
    }
}
