<?php

namespace App\Support;

/**
 * Masks owner-controlled personal data before it reaches the admin shell.
 * Tenant details belong to the owner (Roofly is their data processor), so
 * admins see enough to tell tenants apart and nothing more. Mirrored on the
 * frontend by utils/privacyMask.ts for the demo adapter — keep them in step.
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

    /** "aminah.yusof@example.com" → "am•••@example.com". */
    public static function email(?string $email): string
    {
        $email = trim((string) $email);
        $at = mb_strrpos($email, '@');
        if ($at === false || $at === 0) {
            return self::DOTS;
        }
        $local = mb_substr($email, 0, $at);
        $keep = mb_strlen($local) > 2 ? 2 : 1;

        return mb_substr($local, 0, $keep) . self::DOTS . mb_substr($email, $at);
    }
}
