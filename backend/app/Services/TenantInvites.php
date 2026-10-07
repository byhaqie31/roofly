<?php

namespace App\Services;

use App\Models\TenantInvite;
use App\Models\User;
use App\Notifications\TenantInvite as TenantInviteNotification;
use Illuminate\Support\Str;

/**
 * Issues the set-password link for an invited tenant (spec 2026-10-07 § 4).
 *
 * Only a sha256 of the token is stored, so the plain link exists exactly once:
 * in the return value of issue()/send(). Owner invite and admin resend email
 * it; the owner's "copy invite link" backup (§ 4.3) returns it without mail.
 * Issuing voids every earlier link for the tenant — only the newest works.
 */
class TenantInvites
{
    public const INVITE_DAYS = 7;

    public static function urlFor(User $tenant, string $plainToken): string
    {
        return rtrim(config('app.frontend_url'), '/')
            . '/auth/accept-invite?token=' . $plainToken
            . '&email=' . urlencode($tenant->email);
    }

    /** @return array{invite: TenantInvite, url: string} */
    public function issue(User $tenant): array
    {
        TenantInvite::where('user_id', $tenant->id)->whereNull('accepted_at')->update(['accepted_at' => now()]);

        $plain  = Str::random(48);
        $invite = TenantInvite::create([
            'user_id'    => $tenant->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(self::INVITE_DAYS),
        ]);

        return ['invite' => $invite, 'url' => self::urlFor($tenant, $plain), 'plain' => $plain];
    }

    /** issue() + the queued email. @return array{invite: TenantInvite, url: string} */
    public function send(User $tenant): array
    {
        $issued = $this->issue($tenant);
        $tenant->notify(new TenantInviteNotification($issued['plain'], $tenant->inviter?->name));
        unset($issued['plain']);

        return $issued;
    }
}
