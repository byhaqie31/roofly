<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TrackTest extends TestCase
{
    use RefreshDatabase;

    private const VID = '44444444-4444-4444-8444-444444444444';

    public function test_accepts_whitelisted_event_as_guest(): void
    {
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'page_view', 'path' => '/coming-soon', 'referrer' => 'x.com'])->assertNoContent();
        $this->assertSame(1, AnalyticsEvent::count());
        $this->assertSame('/coming-soon', AnalyticsEvent::first()->path);
    }

    public function test_rejects_bad_payloads(): void
    {
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'evil'])->assertUnprocessable();
        $this->postJson('/api/track', ['visitorId' => 'nope', 'event' => 'page_view'])->assertUnprocessable();
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'waitlist_signup', 'props' => ['email' => 'not-an-email']])->assertUnprocessable();
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'page_view', 'props' => ['blob' => str_repeat('x', 3000)]])->assertUnprocessable();
        $this->assertSame(0, AnalyticsEvent::count());
    }

    public function test_waitlist_event_creates_lead(): void
    {
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'waitlist_signup', 'props' => ['email' => 'a@b.my']])->assertNoContent();
        $this->assertSame('a@b.my', Lead::first()->email);
    }

    public function test_is_rate_limited(): void
    {
        RateLimiter::clear('track:127.0.0.1');
        for ($i = 0; $i < 120; $i++) {
            $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'page_view'])->assertNoContent();
        }
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'page_view'])->assertStatus(429);
    }

    public function test_track_endpoint_cannot_forge_conversion(): void
    {
        $someoneElse = User::factory()->owner()->create(['email' => 'real@owner.my']);
        Lead::factory()->create(['email' => 'a@b.my']);
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'register', 'props' => ['email' => 'a@b.my', 'userId' => $someoneElse->id]])->assertNoContent();
        $this->assertNull(Lead::first()->converted_user_id);
    }

    public function test_accepts_beacon_from_frontend_origin(): void
    {
        $this->withHeaders([
            'Origin'   => config('app.frontend_url'),
            'Referer'  => config('app.frontend_url').'/coming-soon',
        ])->postJson('/api/track', [
            'visitorId' => self::VID,
            'event'     => 'page_view',
            'path'      => '/coming-soon',
            'referrer'  => 'x.com',
        ])->assertNoContent();
    }

    public function test_register_links_lead_by_email(): void
    {
        Lead::factory()->create(['email' => 'n@o.my']);
        $res = $this->postJson('/api/auth/register', [
            'name' => 'New Owner', 'email' => 'n@o.my', 'phone' => '+60 12', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'visitorId' => self::VID,
        ])->assertCreated();
        $this->assertSame($res->json('user.id'), Lead::first()->converted_user_id);
    }
}
