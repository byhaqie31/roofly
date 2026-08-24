<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->owner(),
            'name'     => fake()->streetName() . ' Residence',
            'type'     => 'condo',
            'purpose'  => 'rental',
            'address'  => fake()->streetAddress(),
            'city'     => 'Kuala Lumpur',
            'state'    => 'W.P. Kuala Lumpur',
            'postcode' => '50450',
        ];
    }
}
