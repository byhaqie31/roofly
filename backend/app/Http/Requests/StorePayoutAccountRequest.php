<?php

namespace App\Http\Requests;

class StorePayoutAccountRequest extends PayoutAccountRequest
{
    protected function presence(): string
    {
        return 'required';
    }
}
