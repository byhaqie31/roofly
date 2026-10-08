<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgreementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id'              => Unit::factory(),
            'tenant_id'            => User::factory()->tenant(),
            'start_date'           => '2026-01-01',
            'end_date'             => '2026-12-31',
            'rent_amount_cents'    => 180000,
            'deposit_amount_cents' => 360000,
            'late_fee_cents'       => 5000,
            'rent_due_day'         => 1,
            'status'               => 'active',
        ];
    }
}
