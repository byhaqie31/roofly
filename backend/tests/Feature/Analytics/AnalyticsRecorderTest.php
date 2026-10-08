<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use App\Services\AnalyticsRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsRecorderTest extends TestCase
{
    use RefreshDatabase;

    private const VID = '22222222-2222-4222-8222-222222222222';

    private function rec(): AnalyticsRecorder { return app(AnalyticsRecorder::class); }

    public function test_record_stores_event_with_hashed_ip_and_truncated_ua(): void
    {
        $e = $this->rec()->record(['visitorId' => self::VID, 'event' => 'page_view', 'path' => '/demo', 'referrer' => 'google.com', 'utm' => ['source' => 'fb']], '203.0.113.9', str_repeat('U', 400));
        $this->assertSame(hash('sha256', '203.0.113.9' . config('app.key')), $e->ip_hash);
        $this->assertSame(255, strlen($e->user_agent));
        $this->assertSame(['source' => 'fb'], $e->utm);
        $this->assertSame(0, Lead::count());
    }

    public function test_waitlist_signup_creates_lead_and_second_event_bumps_last_seen(): void
    {
        $this->travelTo(now()->subDays(2));
        $this->rec()->record(['visitorId' => self::VID, 'event' => 'waitlist_signup', 'props' => ['email' => 'Lead@Example.com']], null, null);
        $lead = Lead::first();
        $this->assertSame('lead@example.com', $lead->email);
        $this->assertSame('waitlist', $lead->source);
        $this->assertSame(self::VID, $lead->visitor_id);
        $first = $lead->first_seen_at;

        $this->travelBack();
        $this->rec()->record(['visitorId' => self::VID, 'event' => 'demo_enter', 'props' => ['role' => 'owner']], null, null);
        $lead->refresh();
        $this->assertTrue($lead->first_seen_at->equalTo($first));
        $this->assertTrue($lead->last_seen_at->gt($first));
        $this->assertSame(1, Lead::count());
    }

    public function test_register_event_does_not_convert_lead_by_itself(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'new@owner.my']);
        Lead::factory()->create(['email' => 'new@owner.my', 'visitor_id' => self::VID]);
        $this->rec()->record(['visitorId' => self::VID, 'event' => 'register', 'props' => ['email' => 'new@owner.my', 'userId' => $owner->id]], null, null);
        $this->assertNull(Lead::first()->converted_user_id, 'client-supplied register event must never convert a lead');
        $this->assertSame('waitlist', Lead::first()->source, 'source is first-touch, not overwritten');
    }

    public function test_register_without_prior_lead_creates_unconverted_register_lead(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'fresh@owner.my']);
        $this->rec()->record(['visitorId' => self::VID, 'event' => 'register', 'props' => ['email' => 'fresh@owner.my', 'userId' => $owner->id]], null, null);
        $lead = Lead::first();
        $this->assertSame('register', $lead->source);
        $this->assertNull($lead->converted_user_id, 'client-supplied register event must never convert a lead');
    }

    public function test_link_registration_by_email_with_different_visitor(): void
    {
        Lead::factory()->create(['email' => 'x@y.my', 'visitor_id' => '33333333-3333-4333-8333-333333333333']);
        $owner = User::factory()->owner()->create(['email' => 'x@y.my']);
        $this->rec()->linkRegistration($owner, null);
        $this->assertSame($owner->id, Lead::first()->converted_user_id);
        $this->assertSame(1, Lead::count());
    }

    public function test_record_rejects_unknown_event(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->rec()->record(['visitorId' => self::VID, 'event' => 'nope'], null, null);
    }

    public function test_record_rejects_oversized_props(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->rec()->record([
            'visitorId' => self::VID,
            'event'     => 'page_view',
            'props'     => ['blob' => str_repeat('x', AnalyticsRecorder::MAX_PROPS_BYTES + 1)],
        ], null, null);
    }
}
