<?php

namespace App\Models;

use App\Enums\AgreementStatus;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Unit extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'property_id',
        'label',
        'bedrooms',
        'bathrooms',
        'sqft',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => UnitStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class, 'unit_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'unit_id');
    }

    public function activeAgreement(): ?Agreement
    {
        return $this->agreements()
            ->where('status', AgreementStatus::ACTIVE->value)
            ->latest()
            ->first();
    }
}
