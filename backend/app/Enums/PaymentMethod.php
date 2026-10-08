<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case FPX = 'fpx';
    case CARD = 'card';
    case CASH = 'cash';
    case TRANSFER = 'transfer';
}
