<?php
namespace Database\Factories;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Lead> */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email'         => fake()->unique()->safeEmail(),
            'visitor_id'    => (string) Str::uuid(),
            'source'        => 'waitlist',
            'first_seen_at' => now()->subDays(3),
            'last_seen_at'  => now(),
        ];
    }

    public function converted(User $user): static
    {
        return $this->state(fn () => ['converted_user_id' => $user->id, 'source' => 'register']);
    }
}
