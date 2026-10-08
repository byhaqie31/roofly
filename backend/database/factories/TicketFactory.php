<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id'       => Unit::factory(),
            'reporter_id'   => User::factory()->tenant(),
            'reporter_role' => 'tenant',
            'category'      => 'plumbing',
            'priority'      => 'medium',
            'title'         => fake()->sentence(4),
            'description'   => fake()->paragraph(),
            'status'        => 'new',
        ];
    }
}
