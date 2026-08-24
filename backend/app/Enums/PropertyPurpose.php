<?php

namespace App\Enums;

enum PropertyPurpose: string
{
    case RENTAL = 'rental';
    case OWN_STAY = 'own_stay';
    case INVESTMENT = 'investment';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
