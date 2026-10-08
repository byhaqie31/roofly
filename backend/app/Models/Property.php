<?php

namespace App\Models;

use App\Enums\FurnishingStatus;
use App\Enums\PropertyPurpose;
use App\Enums\PropertyType;
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

class Property extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'internal_label',
        'type',
        'purpose',
        'notes',
        'address',
        'city',
        'state',
        'postcode',
        'year_built',
        'built_up_sqft',
        'land_sqft',
        'bedrooms',
        'bathrooms',
        'parking_lots',
        'furnishing',
        'ownership',
        'utilities',
    ];

    protected function casts(): array
    {
        return [
            'type'       => PropertyType::class,
            'purpose'    => PropertyPurpose::class,
            'furnishing' => FurnishingStatus::class,
            'ownership'  => 'array',
            'utilities'  => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function coOwners(): HasMany
    {
        return $this->hasMany(PropertyCoOwner::class, 'property_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'property_id');
    }

    public function scopeRental($query)
    {
        return $query->where('purpose', PropertyPurpose::RENTAL->value);
    }
}
