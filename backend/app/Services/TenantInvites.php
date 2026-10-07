<?php

namespace App\Services;

use App\Models\TenantInvite;
use App\Models\User;
use App\Notifications\TenantInvite as TenantInviteNotification;
use Illuminate\Support\Str;

/**
 * Issues the emailed set-password link for an invited tenant (spec 2026-10-07 § 4).
 * Used by the owner's invite and the admin's resend — only the newest link works.
 */
class TenantInvites
{
    public const INVITE_DAYS = 7;

    public function send(User $tenant): TenantInvite
    {
        TenantInvite::where('user_id', $tenant->id)->whereNull('accepted_at')->update(['accepted_at' => now()]);

        $plain  = Str::random(48);
        $invite = TenantInvite::create([
            'user_id'    => $tenant->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(self::INVITE_DAYS),
        ]);

        $tenant->notify(new TenantInviteNotification($plain, $tenant->inviter?->name));

        return $invite;
    }
}
