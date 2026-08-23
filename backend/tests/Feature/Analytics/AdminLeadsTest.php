<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminLeadsTest extends TestCase
{
    use RefreshDatabase;

    public const LEAD_KEYS = ['id', 'email', 'source', 'firstSeenAt', 'lastSeenAt', 'pageViews', 'demoEntered', 'convertedUserId', 'convertedOwnerName'];
    private const VID = '55555555-5555-4555-8555-555555555555';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo(AdminPermissions::ANALYTICS_VIEW);
        Sanctum::actingAs($admin);
    }

    public function test_list_filters_and_resource_shape(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'Owner Z', 'email' => 'z@x.my']);
        Lead::factory()->converted($owner)->create(['email' => 'z@x.my', 'visitor_id' => self::VID]);
        Lead::factory()->create(['email' => 'wait@x.my', 'source' => 'waitlist']);
        AnalyticsEvent::factory()->pageView('/')->forVisitor(self::VID)->count(3)->create();
        AnalyticsEvent::factory()->event('demo_enter', ['role' => 'owner'])->forVisitor(self::VID)->create();

        $res = $this->getJson('/api/admin/analytics/leads')->assertOk();
        $this->assertSame(['data', 'meta'], array_keys($res->json()));
        $this->assertSame(2, $res->json('meta.total'));
        $row = collect($res->json('data'))->firstWhere('email', 'z@x.my');
        $this->assertSame(self::LEAD_KEYS, array_keys($row));
        $this->assertSame(3, $row['pageViews']);
        $this->assertTrue($row['demoEntered']);
        $this->assertSame('Owner Z', $row['convertedOwnerName']);

        $this->assertSame(1, $this->getJson('/api/admin/analytics/leads?q=wait')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/analytics/leads?source=register')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/analytics/leads?converted=1')->json('meta.total'));
    }

    public function test_list_order_is_deterministic_for_equal_last_seen_at(): void
    {
        $tie = now();
        Lead::factory()->count(3)->create(['last_seen_at' => $tie]);

        $first = $this->getJson('/api/admin/analytics/leads')->json('data');
        $second = $this->getJson('/api/admin/analytics/leads')->json('data');

        $this->assertSame(collect($first)->pluck('id')->all(), collect($second)->pluck('id')->all());
    }

    public function test_show_includes_last_20_events_without_ip_or_ua(): void
    {
        $lead = Lead::factory()->create(['visitor_id' => self::VID, 'email' => 'lead@x.my']);
        AnalyticsEvent::factory()->pageView('/')->forVisitor(self::VID)->count(25)->create();
        AnalyticsEvent::factory()->event('waitlist_signup', ['email' => 'other@x.my'])->forVisitor(self::VID)->at(now()->addMinute())->create();
        $res = $this->getJson("/api/admin/analytics/leads/{$lead->id}")->assertOk();
        $this->assertSame(array_merge(self::LEAD_KEYS, ['events']), array_keys($res->json()));
        $this->assertCount(20, $res->json('events'));
        $this->assertSame(['id', 'event', 'path', 'props', 'createdAt'], array_keys($res->json('events.0')));
        $this->assertStringNotContainsString('ip_hash', json_encode($res->json()));
        $this->assertStringNotContainsString('PHPUnit', json_encode($res->json()));

        $waitlistEvent = collect($res->json('events'))->firstWhere('event', 'waitlist_signup');
        $this->assertNotNull($waitlistEvent);
        $this->assertSame('lead@x.my', $waitlistEvent['props']['email']);
        $this->assertStringNotContainsString('other@x.my', json_encode($res->json()));
    }

    public function test_export_csv_and_audit(): void
    {
        Lead::factory()->count(2)->create();
        $res = $this->get('/api/admin/analytics/leads/export.csv')->assertOk();
        $this->assertStringStartsWith('text/csv', $res->headers->get('content-type'));
        $lines = explode("\n", trim($res->streamedContent()));
        $this->assertSame('email,source,firstSeenAt,lastSeenAt,pageViews,demoEntered,convertedOwnerName', $lines[0]);
        $this->assertCount(3, $lines);
        $this->assertSame('analytics.exported', Activity::inLog('admin')->latest('id')->first()->event);
    }

    public function test_requires_permission(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/admin/analytics/leads')->assertForbidden();
    }
}
