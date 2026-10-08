<?php

namespace App\Support;

/**
 * Masks owner-controlled personal data before it reaches the admin shell.
 * Tenant details belong to the owner (Roofly is their data processor), so
 * admins see a shortened tenant name — enough to tell tenants apart. Mirrored
 * on the frontend by utils/privacyMask.ts for the demo adapter — keep in step.
 */
class PrivacyMask
{
    public const DOTS = '•••';

    /** "Aminah Binti Yusof" → "Aminah Y."; one word stays as is. */
    public static function name(?string $name): string
    {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return self::DOTS;
        }
        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.';
    }
}
