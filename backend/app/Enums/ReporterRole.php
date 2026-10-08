<?php

namespace App\Enums;

enum ReporterRole: string
{
    case OWNER = 'owner';
    case TENANT = 'tenant';
}
