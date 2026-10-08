<?php

namespace App\Enums;

enum FurnishingStatus: string
{
    case UNFURNISHED = 'unfurnished';
    case PARTIAL = 'partial';
    case FULLY = 'fully';
}
