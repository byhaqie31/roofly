<?php
namespace Database\Factories;

use App\Models\AnalyticsEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** @extends Factory<AnalyticsEvent> */
class AnalyticsEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visitor_id' => (string) Str::uuid(),
            'event'      => 'page_view',
            'path'       => '/',
            'referrer'   => null,
            'utm'        => null,
            'props'      => null,
            'ip_hash'    => hash('sha256', '127.0.0.1'),
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
        ];
    }

    public function pageView(string $path = '/'): static { return $this->state(fn () => ['event' => 'page_view', 'path' => $path]); }
    public function event(string $name, array $props = []): static { return $this->state(fn () => ['event' => $name, 'props' => $props ?: null]); }
    public function forVisitor(string $visitorId): static { return $this->state(fn () => ['visitor_id' => $visitorId]); }
    public function at(Carbon $when): static { return $this->state(fn () => ['created_at' => $when]); }
}
