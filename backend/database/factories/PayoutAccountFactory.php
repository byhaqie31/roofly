<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayoutAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id'            => User::factory()->owner(),
            'label'               => 'Personal Maybank',
            'bank'                => 'maybank',
            'account_holder_name' => fake()->name(),
            'account_number'      => fake()->numerify('5140########'),
            'duitnow_id_type'     => 'phone',
            'duitnow_id'          => '+6012' . fake()->numerify('#######'),
            'is_default'          => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
