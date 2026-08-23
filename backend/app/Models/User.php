<?php

namespace App\Models;

use App\Enums\PlanTier;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'password',
        'invited_at',
        'status',
        'invited_by',
        'is_super_admin',
        'suspended_at',
        'suspension_reason',
        'last_active_at',
        'first_login_at',
        'disabled_at',
        'business_name',
        'bank_account_last4',
        'photo_path',
        'plan_tier',
        'owner_preferences',
        'notification_preferences',
        'personal_info',
        'emergency_contact',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role'                     => UserRole::class,
            'plan_tier'                => PlanTier::class,
            'email_verified_at'        => 'datetime',
            'invited_at'               => 'datetime',
            'is_super_admin'           => 'boolean',
            'suspended_at'             => 'datetime',
            'last_active_at'           => 'datetime',
            'first_login_at'           => 'datetime',
            'disabled_at'              => 'datetime',
            'password'                 => 'hashed',
            'owner_preferences'        => 'array',
            'notification_preferences' => 'array',
            'personal_info'            => 'array',
            'emergency_contact'        => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()
            ->logExcept(['last_active_at', 'first_login_at']);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    public function ownedUnits(): HasManyThrough
    {
        return $this->hasManyThrough(Unit::class, Property::class, 'owner_id', 'property_id');
    }

    public function invitedTenants(): HasMany
    {
        return $this->hasMany(User::class, 'invited_by');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function coOwnerships(): HasMany
    {
        return $this->hasMany(PropertyCoOwner::class, 'user_id');
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class, 'tenant_id');
    }

    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenancy::class, 'tenant_id');
    }

    public function reportedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'reporter_id');
    }

    public function ticketComments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'author_id');
    }

    public function adminInvites(): HasMany
    {
        return $this->hasMany(AdminInvite::class, 'user_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOwner(): bool
    {
        return $this->role === UserRole::OWNER;
    }

    public function isTenant(): bool
    {
        return $this->role === UserRole::TENANT;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }
}
