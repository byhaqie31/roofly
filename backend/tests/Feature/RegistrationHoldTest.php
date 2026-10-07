<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GoogleIdToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** REGISTRATION_OPEN=false — production during the beta-tester hunt. (Open is the default: OwnerWelcomeTest registers freely.) */
class RegistrationHoldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.registration_open' => false]);
        Notification::fake();
    }

    private function fakeGoogle(string $email): void
    {
        $this->mock(GoogleIdToken::class, fn ($m) => $m->shouldReceive('verify')->andReturn([
            'sub' => 'g-123', 'email' => $email, 'name' => 'Google Owner', 'picture' => 'https://img/pic',
        ]));
    }

    public function test_password_registration_is_refused(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'New Owner', 'email' => 'n@o.my', 'phone' => '+60 12',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertForbidden()->assertJsonPath('code', 'registration_closed');

        $this->assertSame(0, User::where('email', 'n@o.my')->count());
        Notification::assertNothingSent();
    }

    public function test_google_cannot_create_a_new_owner(): void
    {
        $this->fakeGoogle('new@example.com');

        $this->postJson('/api/auth/google', ['credential' => 'tok'])
            ->assertForbidden()->assertJsonPath('code', 'registration_closed');

        $this->assertSame(0, User::where('email', 'new@example.com')->count());
        Notification::assertNothingSent();
    }

    public function test_existing_owner_can_still_sign_in_with_google(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'old@example.com', 'onboarded_at' => now()]);
        $this->fakeGoogle('old@example.com');

        $this->postJson('/api/auth/google', ['credential' => 'tok'])->assertOk()->assertJsonPath('user.id', $owner->id);
    }
}
