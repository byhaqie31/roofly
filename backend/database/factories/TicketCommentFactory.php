<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id'   => Ticket::factory(),
            'author_id'   => User::factory()->tenant(),
            'author_role' => 'tenant',
            'body'        => fake()->sentence(),
        ];
    }
}
