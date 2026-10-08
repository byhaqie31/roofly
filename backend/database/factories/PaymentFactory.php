<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id'   => Invoice::factory(),
            'amount_cents' => 180000,
            'method'       => 'fpx',
            'status'       => 'successful',
            'paid_at'      => now(),
        ];
    }
}
