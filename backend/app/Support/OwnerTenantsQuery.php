<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Tenants visible to one owner: invited by them, or on an agreement for a unit they own. */
final class OwnerTenantsQuery
{
    public static function for(string $ownerId): Builder
    {
        return User::query()
            ->where('role', UserRole::TENANT)
            ->where(fn ($q) => $q
                ->where('invited_by', $ownerId)
                ->orWhereHas('agreements.unit.property', fn ($qq) => $qq->where('owner_id', $ownerId))
            );
    }
}
