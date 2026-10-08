<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\OwnerWelcome;
use App\Support\GoogleIdToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OwnerWelcomeTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(string $email): void
    {
        $this->mock(GoogleIdToken::class, fn ($m) => $m->shouldReceive('verify')->andReturn([
            'sub' => 'g-123', 'email' => $email, 'name' => 'Google Owner', 'picture' => 'https://img/pic',
        ]));
    }

    public function test_password_registration_sends_welcome(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'New Owner', 'email' => 'n@o.my', 'phone' => '+60 12',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertCreated();

        Notification::assertSentTo(User::where('email', 'n@o.my')->firstOrFail(), OwnerWelcome::class);
        Notification::assertSentTimes(OwnerWelcome::class, 1);
    }

    public function test_failed_registration_sends_nothing(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'New Owner', 'email' => 'n@o.my',
            'password' => 'secret123', 'password_confirmation' => 'different1',
        ])->assertUnprocessable();

        Notification::assertNothingSent();
    }

    public function test_first_google_sign_in_sends_welcome(): void
    {
        Notification::fake();
        $this->fakeGoogle('new@example.com');

        $this->postJson('/api/auth/google', ['credential' => 'tok'])->assertCreated();

        Notification::assertSentTo(User::where('email', 'new@example.com')->firstOrFail(), OwnerWelcome::class);
    }

    public function test_returning_google_sign_in_sends_nothing(): void
    {
        Notification::fake();
        User::factory()->owner()->create(['email' => 'old@example.com', 'onboarded_at' => now()]);
        $this->fakeGoogle('old@example.com');

        $this->postJson('/api/auth/google', ['credential' => 'tok'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_email_content(): void
    {
        config(['app.frontend_url' => 'https://uat.roofly.my/']);
        $owner = User::factory()->owner()->make(['name' => "Aminah O'Neil Yusof"]);

        $mail = (new OwnerWelcome)->toMail($owner);
        $html = $mail->render();

        $this->assertSame('Welcome to Roofly', $mail->subject);
        $this->assertStringContainsString('WELCOME ABOARD', $html);
        $this->assertStringContainsString('Hi Aminah,', $html);
        $this->assertStringContainsString('https://uat.roofly.my/owner', $html);
        $this->assertStringContainsString('Go to your dashboard', $html);
        $this->assertStringContainsString('Akaun Roofly anda sudah sedia', $html);
    }

    public function test_falls_back_to_generic_greeting_without_a_name(): void
    {
        $owner = User::factory()->owner()->make(['name' => '']);

        $this->assertStringContainsString('Hi there,', (new OwnerWelcome)->toMail($owner)->render());
    }
}
