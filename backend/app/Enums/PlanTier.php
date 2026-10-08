<?php

namespace App\Enums;

enum PlanTier: string
{
    case FREE = 'free';
    case STARTER = 'starter';
    case PRO = 'pro';
    case BUSINESS = 'business';
}
