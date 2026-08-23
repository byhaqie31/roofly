<?php

namespace App\Support;

use App\Enums\PlanTier;

/** Plan tier → units cap. Null = unlimited. Mirrors Owner\AccountController::plans(). */
final class PlanCaps
{
    public static function unitsCap(?PlanTier $tier): ?int
    {
        return match ($tier) {
            PlanTier::STARTER  => 5,
            PlanTier::PRO      => 25,
            PlanTier::BUSINESS => null,
            default            => 2, // FREE and unset
        };
    }
}
