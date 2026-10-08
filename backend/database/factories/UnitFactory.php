<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'label'       => 'Unit ' . fake()->numerify('##-##'),
            'status'      => 'vacant',
        ];
    }
}
