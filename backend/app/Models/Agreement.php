<?php

namespace App\Models;

use App\Enums\AgreementStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Agreement extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'unit_id',
        'tenant_id',
        'start_date',
        'end_date',
        'rent_amount_cents',
        'deposit_amount_cents',
        'late_fee_cents',
        'rent_due_day',
        'status',
        'sent_at',
        'accepted_at',
        'changes_requested_at',
        'review_note',
        'payout_account_id',
    ];

    /** Columns the tenant agreed to — changing any of them while under review drops back to draft. */
    public const TERM_COLUMNS = [
        'unit_id', 'tenant_id', 'start_date', 'end_date',
        'rent_amount_cents', 'deposit_amount_cents', 'late_fee_cents', 'rent_due_day',
    ];

    protected function casts(): array
    {
        return [
            'status'     => AgreementStatus::class,
            'start_date' => 'date',
            'end_date'   => 'date',
            'sent_at'              => 'datetime',
            'accepted_at'          => 'datetime',
            'changes_requested_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function tenancy(): HasOne
    {
        return $this->hasOne(Tenancy::class, 'agreement_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'agreement_id');
    }

    /** Explicit override; null means "the owner's default" (spec 2026-10-08 § 3.2). */
    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class, 'payout_account_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Where the tenant pays: this agreement's own account ?? the owner's default ?? null.
     * Eager-load `payoutAccount` + `unit.property.owner.defaultPayoutAccount` on lists.
     */
    public function resolvedPayoutAccount(): ?PayoutAccount
    {
        return $this->payoutAccount
            ?? $this->unit?->property?->owner?->defaultPayoutAccount;
    }

    /** Relations resolvedPayoutAccount() walks — prefix with the path to the agreement when eager-loading. */
    public const PAYOUT_RELATIONS = ['payoutAccount', 'unit.property.owner.defaultPayoutAccount'];
}
