<?php

namespace App\Enums;

/** DuitNow proxy id kinds a payout account can carry (spec 2026-10-08 § 3.1). */
enum DuitNowIdType: string
{
    case PHONE = 'phone';
    case MYKAD = 'mykad';
    case BRN = 'brn';
    case PASSPORT = 'passport';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
