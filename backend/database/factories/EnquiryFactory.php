<?php

namespace Database\Factories;

use App\Models\Enquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Enquiry> */
class EnquiryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'     => fake()->name(),
            'email'    => fake()->unique()->safeEmail(),
            'role'     => 'owner',
            'type'     => 'issue',
            'message'  => fake()->sentence(12),
            'page_url' => '/owner/payments',
            'status'   => 'new',
        ];
    }
}
