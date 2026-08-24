<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GoogleIdToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(?array $profile): void
    {
        $this->mock(GoogleIdToken::class, fn ($m) => $m->shouldReceive('verify')->andReturn($profile));
    }

    private function profile(string $email = 'new@example.com'): array
    {
        return ['sub' => 'g-123', 'email' => $email, 'name' => 'Google Owner', 'picture' => 'https://img/pic'];
    }

    public function test_creates_owner_and_logs_in(): void
    {
        $this->fakeGoogle($this->profile());
        $res = $this->postJson('/api/auth/google', ['credential' => 'tok'])->assertCreated();
        $this->assertSame(AuthContractTest::AUTH_USER_KEYS, array_keys($res->json('user')));
        $this->assertSame('owner', $res->json('user.role'));
        $this->assertFalse($res->json('user.hasPassword'));
        $this->assertNull($res->json('user.onboardedAt'));
        $this->assertNotEmpty($res->json('token'));
        $u = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('g-123', $u->google_id);
        $this->assertNotNull($u->email_verified_at);
        $this->assertSame('auth.google_register', Activity::latest('id')->first()->event);
    }

    public function test_links_existing_password_owner(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'old@example.com', 'onboarded_at' => now()]);
        $this->fakeGoogle($this->profile('old@example.com'));
        $res = $this->postJson('/api/auth/google', ['credential' => 'tok'])->assertOk();
        $this->assertSame($owner->id, $res->json('user.id'));
        $this->assertTrue($res->json('user.hasPassword'));
        $this->assertSame('g-123', $owner->fresh()->google_id);
        $this->assertSame(1, User::where('email', 'old@example.com')->count());
        $this->assertSame('auth.google_login', Activity::latest('id')->first()->event);
    }

    public function test_tenant_email_is_forbidden(): void
    {
        User::factory()->tenant()->create(['email' => 't@example.com']);
        $this->fakeGoogle($this->profile('t@example.com'));
        $this->postJson('/api/auth/google', ['credential' => 'tok'])
            ->assertForbidden()->assertJsonPath('code', 'not_owner');
    }

    public function test_invalid_token_is_unauthorized(): void
    {
        $this->fakeGoogle(null);
        $this->postJson('/api/auth/google', ['credential' => 'tok'])->assertUnauthorized();
        $this->assertSame(0, User::count());
    }

    public function test_credential_is_required(): void
    {
        $this->postJson('/api/auth/google', [])->assertStatus(422);
    }

    public function test_password_login_on_google_only_account_is_422(): void
    {
        User::factory()->owner()->create(['email' => 'g@example.com', 'password' => null, 'google_id' => 'g-1']);
        $this->postJson('/api/auth/login', ['email' => 'g@example.com', 'password' => 'whatever'])
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
    }
}
