<?php

namespace Tests\Feature\Analytics;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\WaitlistInvitation;
use App\Services\AuditLogger;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminLeadInviteTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(array $permissions): User
    {
        $this->seed(AdminPermissionSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo($permissions);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function url(Lead $lead): string
    {
        return "/api/admin/analytics/leads/{$lead->id}/invite";
    }

    public function test_sends_invitation_marks_lead_and_audits(): void
    {
        Notification::fake();
        $admin = $this->actingAsAdmin([AdminPermissions::ANALYTICS_VIEW, AdminPermissions::BROADCAST_SEND]);
        $lead = Lead::factory()->create(['email' => 'wait@x.my', 'source' => 'waitlist']);

        $res = $this->postJson($this->url($lead))->assertOk();

        $this->assertSame(AdminLeadsTest::LEAD_KEYS, array_keys($res->json()));
        $this->assertNotNull($res->json('invitedAt'));
        $this->assertNotNull($lead->fresh()->invited_at);
        Notification::assertSentOnDemand(
            WaitlistInvitation::class,
            fn ($n, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'wait@x.my',
        );

        $log = Activity::latest('id')->first();
        $this->assertSame(AuditLogger::LEAD_INVITED, $log->event);
        $this->assertSame($admin->id, $log->causer_id);
        $this->assertSame($lead->id, $log->subject_id);
        $this->assertNull($log->properties['before']['invitedAt']);
    }

    public function test_resend_bumps_invited_at(): void
    {
        Notification::fake();
        $this->actingAsAdmin([AdminPermissions::ANALYTICS_VIEW, AdminPermissions::BROADCAST_SEND]);
        $lead = Lead::factory()->invited()->create(['source' => 'waitlist']);
        $first = $lead->invited_at;

        $this->postJson($this->url($lead))->assertOk();

        $this->assertTrue($lead->fresh()->invited_at->gt($first));
        Notification::assertSentOnDemandTimes(WaitlistInvitation::class, 1);
    }

    public function test_requires_broadcast_send(): void
    {
        Notification::fake();
        $this->actingAsAdmin([AdminPermissions::ANALYTICS_VIEW]);
        $lead = Lead::factory()->create(['source' => 'waitlist']);

        $this->postJson($this->url($lead))->assertForbidden();

        Notification::assertNothingSent();
        $this->assertNull($lead->fresh()->invited_at);
    }

    public function test_refuses_converted_lead_or_existing_account(): void
    {
        Notification::fake();
        $this->actingAsAdmin([AdminPermissions::ANALYTICS_VIEW, AdminPermissions::BROADCAST_SEND]);
        $owner = User::factory()->owner()->create(['email' => 'own@x.my']);
        $converted = Lead::factory()->converted($owner)->create(['source' => 'waitlist']);
        $unlinked = Lead::factory()->create(['email' => 'own@x.my', 'source' => 'waitlist']);

        $this->postJson($this->url($converted))->assertStatus(409);
        $this->postJson($this->url($unlinked))->assertStatus(409);

        Notification::assertNothingSent();
    }

    public function test_refuses_non_waitlist_lead(): void
    {
        Notification::fake();
        $this->actingAsAdmin([AdminPermissions::ANALYTICS_VIEW, AdminPermissions::BROADCAST_SEND]);
        $lead = Lead::factory()->create(['source' => 'demo']);

        $this->postJson($this->url($lead))->assertStatus(409);

        Notification::assertNothingSent();
    }

    public function test_email_links_to_invite_signup_url_with_email_prefilled(): void
    {
        // Production sets INVITE_SIGNUP_URL to UAT's register page during the beta hunt.
        config(['app.invite_signup_url' => 'https://uat.example.test/auth/register']);

        $mail = (new WaitlistInvitation('a+b@x.my'))->toMail(new AnonymousNotifiable);
        $html = $mail->render();

        $this->assertSame('Your Roofly invitation is here', $mail->subject);
        $this->assertStringContainsString('YOUR INVITATION HAS ARRIVED', $html);
        $this->assertStringContainsString('https://uat.example.test/auth/register?email=a%2Bb%40x.my', $html);
        $this->assertStringContainsString('Create your Roofly account', $html);
    }

    public function test_invite_signup_url_defaults_to_this_environments_register_page(): void
    {
        $this->assertSame(rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/') . '/auth/register', config('app.invite_signup_url'));
    }
}
