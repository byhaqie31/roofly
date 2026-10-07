<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\AdminNewEnquiry;
use App\Notifications\AdminNewOwnerSignup;
use App\Support\GoogleIdToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SuperAdminAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $super;
    private User $ops;
    private User $disabledSuper;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->super = User::factory()->superAdmin()->create();
        $this->ops = User::factory()->admin()->create();
        $this->disabledSuper = User::factory()->superAdmin()->create(['disabled_at' => now()]);
    }

    private function fakeGoogle(string $email): void
    {
        $this->mock(GoogleIdToken::class, fn ($m) => $m->shouldReceive('verify')->andReturn([
            'sub' => 'g-123', 'email' => $email, 'name' => 'Google Owner', 'picture' => 'https://img/pic',
        ]));
    }

    private function assertOnlyActiveSuperAdminsGot(string $notification): void
    {
        Notification::assertSentTo($this->super, $notification);
        Notification::assertNotSentTo($this->ops, $notification);
        Notification::assertNotSentTo($this->disabledSuper, $notification);
    }

    public function test_new_waitlist_enquiry_alerts_active_super_admins(): void
    {
        $this->postJson('/api/waitlist', ['email' => 'New@X.my'])->assertNoContent();

        $this->assertOnlyActiveSuperAdminsGot(AdminNewEnquiry::class);
        Notification::assertSentTo($this->super, AdminNewEnquiry::class, fn ($n) => $n->email === 'new@x.my');
    }

    public function test_repeat_enquiry_and_honeypot_send_no_alert(): void
    {
        Lead::factory()->create(['email' => 'a@b.my', 'source' => 'waitlist']);

        $this->postJson('/api/waitlist', ['email' => 'a@b.my'])->assertNoContent();
        $this->postJson('/api/waitlist', ['email' => 'bot@x.my', 'website' => 'http://spam'])->assertNoContent();

        Notification::assertNotSentTo($this->super, AdminNewEnquiry::class);
    }

    public function test_password_sign_up_alerts_active_super_admins(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'New Owner', 'email' => 'n@o.my', 'phone' => '+60 12',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertCreated();

        $this->assertOnlyActiveSuperAdminsGot(AdminNewOwnerSignup::class);
        Notification::assertSentTo($this->super, AdminNewOwnerSignup::class, fn ($n) => $n->email === 'n@o.my' && $n->method === 'password');
    }

    public function test_first_google_sign_in_alerts_but_returning_one_does_not(): void
    {
        $this->fakeGoogle('new@example.com');
        $this->postJson('/api/auth/google', ['credential' => 'tok'])->assertCreated();
        Notification::assertSentTo($this->super, AdminNewOwnerSignup::class, fn ($n) => $n->method === 'google');

        Notification::fake(); // reset
        $this->postJson('/api/auth/google', ['credential' => 'tok'])->assertOk();
        Notification::assertNotSentTo($this->super, AdminNewOwnerSignup::class);
    }

    public function test_held_sign_up_sends_no_alert(): void
    {
        config(['app.registration_open' => false]);

        $this->postJson('/api/auth/register', [
            'name' => 'New Owner', 'email' => 'n@o.my',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertForbidden();

        Notification::assertNotSentTo($this->super, AdminNewOwnerSignup::class);
    }

    public function test_alert_emails_render_with_admin_links(): void
    {
        config(['app.frontend_url' => 'https://roofly.my/']);
        $owner = User::factory()->owner()->create(['name' => 'Aminah Yusof', 'email' => 'aminah@x.my', 'phone' => '+60 12 345 6789']);

        $enquiry = AdminNewEnquiry::make('lead@x.my')->toMail($this->super);
        $this->assertSame('New enquiry: lead@x.my', $enquiry->subject);
        $html = $enquiry->render();
        $this->assertStringContainsString('NEW ENQUIRY', $html);
        $this->assertStringContainsString('Coming-soon waitlist', $html);
        $this->assertStringContainsString('https://roofly.my/admin/enquiries', $html);

        $signup = AdminNewOwnerSignup::fromOwner($owner, 'google')->toMail($this->super);
        $this->assertSame('New owner sign-up: Aminah Yusof', $signup->subject);
        $html = $signup->render();
        $this->assertStringContainsString('+60 12 345 6789', $html);
        $this->assertStringContainsString('With Google', $html);
        $this->assertStringContainsString("https://roofly.my/admin/owners/{$owner->id}", $html);
    }
}
