<?php

namespace Database\Factories;

use App\Models\Agreement;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agreement_id'   => Agreement::factory(),
            'invoice_number' => 'INV-' . fake()->unique()->numerify('####'),
            'amount_cents'   => 180000,
            'late_fee_cents' => 0,
            'due_date'       => '2026-07-01',
            'status'         => 'pending',
        ];
    }
}
