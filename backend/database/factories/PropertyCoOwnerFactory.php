<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyCoOwnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name'        => fake()->name(),
            'share_pct'   => 100,
            'is_primary'  => true,
        ];
    }
}
