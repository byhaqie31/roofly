<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class PruneAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_only_events_older_than_13_months(): void
    {
        AnalyticsEvent::factory()->at(now()->subMonths(14))->count(3)->create();
        AnalyticsEvent::factory()->at(now()->subMonths(12))->count(2)->create();
        $this->artisan('analytics:prune')->expectsOutputToContain('Pruned 3')->assertExitCode(0);
        $this->assertSame(2, AnalyticsEvent::count());
    }

    public function test_is_scheduled_daily(): void
    {
        $events = collect(Schedule::events())->map(fn ($e) => $e->command ?? $e->description);
        $this->assertTrue($events->contains(fn ($c) => str_contains((string) $c, 'analytics:prune')));
    }
}
