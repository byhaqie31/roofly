<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /auth/logout must actually sign the SPA out. The SPA authenticates with
 * the `web` session cookie, whose Sanctum "token" is a TransientToken — the old
 * `currentAccessToken()->delete()` 500'd on it and left the session alive, so
 * the next page load signed the user straight back in.
 */
class LogoutTest extends TestCase
{
    use RefreshDatabase;

    /** Stateful (cookie) requests from the SPA origin, like the browser sends. */
    private function spa(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/']);
    }

    /**
     * The test app is reused across requests, so the resolved auth guards keep
     * their user cached. A real browser request boots fresh — mimic that.
     */
    private function nextRequest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->spa();
    }

    public function test_session_logout_ends_the_session_for_every_role(): void
    {
        foreach (['owner', 'tenant', 'admin'] as $role) {
            $user = User::factory()->{$role}()->create();
            $this->actingAs($user, 'web');

            $this->spa()->getJson('/api/auth/me')->assertOk();
            $this->spa()->postJson('/api/auth/logout')->assertNoContent();

            $this->assertGuest('web');
            $this->nextRequest()->getJson('/api/auth/me')->assertUnauthorized();
        }
    }

    public function test_real_admin_sign_in_then_logout_cannot_reach_me(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'ops@example.com', 'password' => 'password']);

        $this->spa()->postJson('/api/admin/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();
        $this->spa()->getJson('/api/auth/me')->assertOk()->assertJsonPath('email', 'ops@example.com');

        $this->spa()->postJson('/api/auth/logout')->assertNoContent();
        $this->nextRequest()->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_bearer_token_logout_revokes_the_token(): void
    {
        $user = User::factory()->owner()->create();
        $plain = $user->createToken('cli')->plainTextToken;

        $this->withToken($plain)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }
}
