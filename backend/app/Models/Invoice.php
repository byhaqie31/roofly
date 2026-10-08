<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Invoice extends Model
{
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'agreement_id',
        'invoice_number',
        'amount_cents',
        'late_fee_cents',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status'   => InvoiceStatus::class,
            'due_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Eager-loads for InvoiceWithRefsResource (incl. the resolved payout account). */
    public static function refsRelations(): array
    {
        return [
            'agreement.unit.property.coOwners',
            'agreement.tenant',
            'payments',
            ...array_map(fn (string $r) => "agreement.{$r}", Agreement::PAYOUT_RELATIONS),
        ];
    }

    public function totalDueCents(): int
    {
        return $this->amount_cents + $this->late_fee_cents;
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'agreement_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    public function successfulPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'invoice_id')
            ->where('status', 'successful');
    }

    /** A tenant's transfer claim awaiting the owner (at most one, spec 2026-10-08 § 3.3). */
    public function pendingPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'invoice_id')
            ->where('status', 'pending');
    }
}
