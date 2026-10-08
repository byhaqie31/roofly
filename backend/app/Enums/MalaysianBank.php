<?php

namespace App\Enums;

/**
 * Banks a payout account can sit at (spec 2026-10-08 § 3.4). Slugs only —
 * the display labels are proper nouns and live in frontend/app/config/banks.ts.
 */
enum MalaysianBank: string
{
    case MAYBANK = 'maybank';
    case CIMB = 'cimb';
    case PUBLIC_BANK = 'public_bank';
    case RHB = 'rhb';
    case HONG_LEONG = 'hong_leong';
    case AMBANK = 'ambank';
    case BANK_ISLAM = 'bank_islam';
    case BANK_RAKYAT = 'bank_rakyat';
    case BSN = 'bsn';
    case AFFIN = 'affin';
    case ALLIANCE = 'alliance';
    case OCBC = 'ocbc';
    case UOB = 'uob';
    case HSBC = 'hsbc';
    case STANDARD_CHARTERED = 'standard_chartered';
    case MUAMALAT = 'muamalat';
    case AGROBANK = 'agrobank';
    case GXBANK = 'gxbank';
    case AEON_BANK = 'aeon_bank';
    case BOOST_BANK = 'boost_bank';
    case OTHER = 'other';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
