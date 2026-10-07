<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Notifications\WaitlistConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** First-party coming-soon waitlist capture (replaces the Web3Forms relay). */
class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    private const VID = '44444444-4444-4444-8444-444444444444';

    public function test_stores_lead_as_guest(): void
    {
        $this->postJson('/api/waitlist', ['email' => ' A@B.my '])->assertNoContent();

        $this->assertSame(1, Lead::count());
        $lead = Lead::first();
        $this->assertSame('a@b.my', $lead->email);
        $this->assertSame('waitlist', $lead->source);
        $this->assertNotNull($lead->first_seen_at);
        $this->assertNull($lead->converted_user_id);
    }

    public function test_repeat_signup_bumps_last_seen_without_duplicate(): void
    {
        $lead = Lead::factory()->create([
            'email' => 'a@b.my', 'source' => 'register',
            'first_seen_at' => now()->subDays(3), 'last_seen_at' => now()->subDays(3),
        ]);

        $this->postJson('/api/waitlist', ['email' => 'a@b.my'])->assertNoContent();

        $this->assertSame(1, Lead::count());
        $fresh = $lead->fresh();
        $this->assertSame('register', $fresh->source, 'source is first-touch and never overwritten');
        $this->assertTrue($fresh->first_seen_at->equalTo($lead->first_seen_at));
        $this->assertTrue($fresh->last_seen_at->greaterThan($lead->last_seen_at));
    }

    public function test_rejects_invalid_or_missing_email(): void
    {
        $this->postJson('/api/waitlist', ['email' => 'not-an-email'])->assertUnprocessable();
        $this->postJson('/api/waitlist', [])->assertUnprocessable();
        $this->postJson('/api/waitlist', ['email' => 'a@b.my', 'visitorId' => 'nope'])->assertUnprocessable();
        $this->assertSame(0, Lead::count());
    }

    public function test_filled_honeypot_is_silently_dropped(): void
    {
        $this->postJson('/api/waitlist', ['email' => 'bot@spam.my', 'website' => 'http://spam.example'])->assertNoContent();

        $this->assertSame(0, Lead::count());
        $this->assertSame(0, AnalyticsEvent::count());
    }

    public function test_records_signup_event_when_visitor_id_given(): void
    {
        $this->postJson('/api/waitlist', ['email' => 'a@b.my', 'visitorId' => self::VID])->assertNoContent();

        $this->assertSame(1, AnalyticsEvent::count());
        $event = AnalyticsEvent::first();
        $this->assertSame('waitlist_signup', $event->event);
        $this->assertSame(self::VID, $event->visitor_id);
        $this->assertSame('a@b.my', $event->props['email']);
        $this->assertSame(self::VID, Lead::first()->visitor_id);
    }

    public function test_no_event_without_visitor_id(): void
    {
        $this->postJson('/api/waitlist', ['email' => 'a@b.my'])->assertNoContent();

        $this->assertSame(0, AnalyticsEvent::count());
        $this->assertSame(1, Lead::count());
        $this->assertNull(Lead::first()->visitor_id);
    }

    public function test_new_signup_gets_one_confirmation_email(): void
    {
        Notification::fake();

        $this->postJson('/api/waitlist', ['email' => ' A@B.my '])->assertNoContent();

        Notification::assertSentOnDemandTimes(WaitlistConfirmation::class, 1);
        Notification::assertSentOnDemand(
            WaitlistConfirmation::class,
            fn ($notification, array $channels, object $notifiable) => $channels === ['mail']
                && $notifiable->routes['mail'] === 'a@b.my',
        );
    }

    public function test_repeat_signup_sends_no_confirmation_email(): void
    {
        Notification::fake();
        Lead::factory()->create(['email' => 'a@b.my', 'source' => 'waitlist']);

        $this->postJson('/api/waitlist', ['email' => 'A@b.my'])->assertNoContent();

        Notification::assertNothingSent();
    }

    public function test_honeypot_sends_no_confirmation_email(): void
    {
        Notification::fake();

        $this->postJson('/api/waitlist', ['email' => 'bot@spam.my', 'website' => 'http://spam.example'])->assertNoContent();

        Notification::assertNothingSent();
    }

    public function test_confirmation_email_content(): void
    {
        config(['app.demo_url' => 'https://demo.example.test']);
        $mail = (new WaitlistConfirmation)->toMail(new AnonymousNotifiable)->render();

        $this->assertStringContainsString('WAITLIST CONFIRMED', $mail);
        $this->assertStringContainsString('Hi there,', $mail);
        $this->assertStringContainsString('Your spot on the Roofly waitlist is confirmed.', $mail);
        $this->assertStringContainsString('senarai menunggu Roofly', $mail);
        $this->assertStringContainsString('https://demo.example.test', $mail);
        $this->assertStringContainsString('The Roofly Team', $mail);
    }

    public function test_confirmation_email_subject(): void
    {
        $mail = (new WaitlistConfirmation)->toMail(new AnonymousNotifiable);

        $this->assertSame("Waitlist confirmation: you're on the Roofly list", $mail->subject);
    }

    public function test_is_rate_limited(): void
    {
        RateLimiter::clear('waitlist:127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/waitlist', ['email' => "p{$i}@b.my"])->assertNoContent();
        }
        $this->postJson('/api/waitlist', ['email' => 'p5@b.my'])->assertStatus(429);
    }

    public function test_accepts_post_from_frontend_origin_without_csrf_token(): void
    {
        $this->withHeaders([
            'Origin'  => config('app.frontend_url'),
            'Referer' => config('app.frontend_url').'/coming-soon',
        ])->postJson('/api/waitlist', ['email' => 'a@b.my'])->assertNoContent();
    }
}
