<?php

namespace App\Http\Requests;

class UpdatePayoutAccountRequest extends PayoutAccountRequest
{
    protected function presence(): string
    {
        return 'sometimes';
    }
}
