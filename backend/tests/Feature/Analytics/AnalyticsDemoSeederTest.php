<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use Database\Seeders\AnalyticsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuses_to_wipe_analytics_data_in_production(): void
    {
        $this->app['env'] = 'production';

        Lead::factory()->create();

        // db:seed itself confirms before running in production; --force bypasses
        // that outer guard so this test exercises the seeder's own internal guard.
        $this->artisan('db:seed', ['--class' => AnalyticsDemoSeeder::class, '--force' => true]);

        $this->assertSame(1, Lead::count());
        $this->assertSame(0, AnalyticsEvent::count());
    }
}
