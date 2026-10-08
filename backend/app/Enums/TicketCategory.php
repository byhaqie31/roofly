<?php

namespace App\Enums;

enum TicketCategory: string
{
    case PLUMBING = 'plumbing';
    case ELECTRICAL = 'electrical';
    case APPLIANCE = 'appliance';
    case STRUCTURAL = 'structural';
    case PEST = 'pest';
    case OTHER = 'other';
}
