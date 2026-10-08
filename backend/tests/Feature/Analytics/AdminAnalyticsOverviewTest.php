<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAnalyticsOverviewTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const C = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo(AdminPermissions::ANALYTICS_VIEW);
        Sanctum::actingAs($admin);
        $this->travelTo(now()->setDate(2026, 8, 20)->setTime(12, 0));
    }

    public function test_overview_shape_and_math(): void
    {
        // A: returning visitor (first seen 40 days ago), 2 views + demo + waitlist in range
        AnalyticsEvent::factory()->pageView('/')->forVisitor(self::A)->at(now()->subDays(40))->create();
        AnalyticsEvent::factory()->pageView('/')->forVisitor(self::A)->at(now()->subDays(3))->create(['referrer' => 'google.com']);
        AnalyticsEvent::factory()->pageView('/demo')->forVisitor(self::A)->at(now()->subDays(3))->create();
        AnalyticsEvent::factory()->event('demo_enter', ['role' => 'owner'])->forVisitor(self::A)->at(now()->subDays(3))->create();
        AnalyticsEvent::factory()->event('waitlist_signup', ['email' => 'a@x.my'])->forVisitor(self::A)->at(now()->subDays(2))->create();
        Lead::factory()->create(['email' => 'a@x.my', 'visitor_id' => self::A, 'first_seen_at' => now()->subDays(2)]);
        // B: new visitor, 1 view, registered
        $owner = User::factory()->owner()->create(['email' => 'b@x.my']);
        AnalyticsEvent::factory()->pageView('/')->forVisitor(self::B)->at(now()->subDay())->create();
        AnalyticsEvent::factory()->event('register', ['email' => 'b@x.my', 'userId' => $owner->id])->forVisitor(self::B)->at(now()->subDay())->create();
        Lead::factory()->converted($owner)->create(['email' => 'b@x.my', 'visitor_id' => self::B, 'first_seen_at' => now()->subDay()]);
        // C: outside range
        AnalyticsEvent::factory()->pageView('/')->forVisitor(self::C)->at(now()->subDays(45))->create();

        $res = $this->getJson('/api/admin/analytics/overview')->assertOk();
        $this->assertSame(['range', 'tiles', 'series', 'funnel', 'topPages', 'referrers'], array_keys($res->json()));
        $this->assertSame(30, $res->json('range.days'));
        $this->assertSame(['views', 'visitors', 'newVisitors', 'demoEntries', 'leads', 'registrations', 'conversionPct'], array_keys($res->json('tiles')));
        $this->assertSame(3, $res->json('tiles.views'));
        $this->assertSame(2, $res->json('tiles.visitors'));
        $this->assertSame(1, $res->json('tiles.newVisitors'));
        $this->assertSame(1, $res->json('tiles.demoEntries'));
        $this->assertSame(2, $res->json('tiles.leads'));
        $this->assertSame(1, $res->json('tiles.registrations'));
        $this->assertSame(50, $res->json('tiles.conversionPct'));
        $this->assertCount(30, $res->json('series.days'));
        $this->assertSame(now()->toDateString(), $res->json('series.days.29'));
        $this->assertSame(3, array_sum($res->json('series.views')));
        $this->assertSame(['visitors' => 2, 'demo' => 1, 'leads' => 2, 'registered' => 1], $res->json('funnel'));
        $this->assertSame(['path' => '/', 'views' => 2], $res->json('topPages.0'));
        $this->assertSame(['direct' => 2, 'google.com' => 1], collect($res->json('referrers'))->pluck('visitors', 'referrer')->all());
        $this->assertContains('direct', collect($res->json('referrers'))->pluck('referrer')->all());
        $this->assertStringNotContainsString('ip_hash', json_encode($res->json()));
    }

    public function test_custom_range_and_cap(): void
    {
        $this->getJson('/api/admin/analytics/overview?from=2026-08-01&to=2026-08-07')->assertOk()->assertJsonPath('range.days', 7);
        $this->getJson('/api/admin/analytics/overview?from=2025-01-01&to=2026-08-20')->assertUnprocessable();
        $this->getJson('/api/admin/analytics/overview?from=2026-08-10&to=2026-08-01')->assertUnprocessable();
        $this->getJson('/api/admin/analytics/overview?from=garbage')->assertUnprocessable();
    }

    public function test_requires_permission(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/admin/analytics/overview')->assertForbidden();
    }
}
