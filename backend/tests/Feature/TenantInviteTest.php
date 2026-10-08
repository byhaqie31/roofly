<?php

namespace Tests\Feature;

use App\Models\TenantInvite as TenantInviteModel;
use App\Models\User;
use App\Notifications\TenantInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Spec: docs/superpowers/specs/2026-10-07-invoice-generation-tenant-invite-design.md § 4.
 */
class TenantInviteTest extends TestCase
{
    use RefreshDatabase;

    private function invite(User $tenant, string $plainToken, array $attrs = []): TenantInviteModel
    {
        return TenantInviteModel::create(array_merge([
            'user_id'    => $tenant->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ], $attrs));
    }

    public function test_owner_invite_emails_a_set_password_link(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create(['name' => 'Aminah']);
        Sanctum::actingAs($owner);

        $this->postJson('/api/tenants/invite', ['name' => 'Adi', 'email' => 'adi@example.com', 'phone' => '+60 1'])
            ->assertCreated();

        $tenant = User::where('email', 'adi@example.com')->firstOrFail();
        Notification::assertSentTo($tenant, TenantInvite::class, function (TenantInvite $n) use ($tenant) {
            $url = $n->url($tenant);
            return str_starts_with($url, rtrim(config('app.frontend_url'), '/') . '/auth/accept-invite?token=')
                && str_contains($url, 'email=adi%40example.com');
        });
        $this->assertSame(1, TenantInviteModel::where('user_id', $tenant->id)->whereNull('accepted_at')->count());
    }

    public function test_accept_sets_password_activates_the_tenant_and_logs_in(): void
    {
        $tenant = User::factory()->invitedTenant()->create(['email' => 'adi@example.com', 'password' => null]);
        $this->invite($tenant, 'plain-token');

        $res = $this->postJson('/api/auth/accept-invite', [
            'token' => 'plain-token', 'email' => 'adi@example.com',
            'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertOk();

        $this->assertSame(['user', 'token'], array_keys($res->json()));
        $this->assertSame('tenant', $res->json('user.role'));
        $fresh = $tenant->fresh();
        $this->assertTrue(Hash::check('newsecret1', $fresh->password));
        $this->assertSame('active', $fresh->status);
        $this->assertNotNull($fresh->first_login_at);
        $this->assertNotNull(TenantInviteModel::where('user_id', $tenant->id)->first()->accepted_at);
    }

    public function test_accept_rejects_expired_used_or_mismatched_links(): void
    {
        $tenant = User::factory()->invitedTenant()->create(['email' => 'adi@example.com', 'password' => null]);
        $this->invite($tenant, 'expired', ['expires_at' => now()->subMinute()]);
        $this->invite($tenant, 'used', ['accepted_at' => now()]);
        $this->invite($tenant, 'live');
        $body = fn (string $token, string $email = 'adi@example.com') => [
            'token' => $token, 'email' => $email, 'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ];

        $this->postJson('/api/auth/accept-invite', $body('expired'))->assertStatus(422)->assertJsonValidationErrors('token');
        $this->postJson('/api/auth/accept-invite', $body('used'))->assertStatus(422)->assertJsonValidationErrors('token');
        $this->postJson('/api/auth/accept-invite', $body('live', 'someone@else.com'))->assertStatus(422)->assertJsonValidationErrors('token');
        $this->postJson('/api/auth/accept-invite', $body('unknown'))->assertStatus(422)->assertJsonValidationErrors('token');

        $this->assertNull($tenant->fresh()->password);
        $this->assertSame('invited', $tenant->fresh()->status);
    }

    public function test_accept_is_for_tenants_only(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'o@example.com']);
        $this->invite($owner, 'owner-token');

        $this->postJson('/api/auth/accept-invite', [
            'token' => 'owner-token', 'email' => 'o@example.com',
            'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertStatus(422)->assertJsonValidationErrors('token');
    }

    public function test_forgot_password_path_also_activates_an_invited_tenant(): void
    {
        $tenant = User::factory()->invitedTenant()->create(['email' => 'adi@example.com', 'password' => null]);
        $token = Password::createToken($tenant);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'adi@example.com',
            'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertOk();

        $this->assertSame('active', $tenant->fresh()->status);
    }

    // ── Owner backup: copy / share the link (spec 2026-10-07 § 4.3) ─────────

    public function test_invite_response_carries_the_same_link_as_the_email(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->owner()->create());

        $res = $this->postJson('/api/tenants/invite', ['name' => 'Adi', 'email' => 'adi@example.com', 'phone' => '+60 1'])
            ->assertCreated();

        $tenant = User::where('email', 'adi@example.com')->firstOrFail();
        $emailed = null;
        Notification::assertSentTo($tenant, TenantInvite::class, function (TenantInvite $n) use ($tenant, &$emailed) {
            $emailed = $n->url($tenant);
            return true;
        });
        $this->assertSame($emailed, $res->json('inviteUrl'));
        $this->assertNotNull($res->json('inviteExpiresAt'));
    }

    public function test_owner_can_mint_a_fresh_invite_link_without_sending_mail(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $tenant = User::factory()->invitedTenant()->create(['email' => 'adi@example.com', 'password' => null, 'invited_by' => $owner->id]);
        $old = $this->invite($tenant, 'old-token');
        Sanctum::actingAs($owner);

        $res = $this->postJson("/api/tenants/{$tenant->id}/invite-link")->assertOk();

        $this->assertSame(['inviteUrl', 'inviteExpiresAt'], array_keys($res->json()));
        $this->assertNotNull($old->fresh()->accepted_at); // earlier links stop working
        Notification::assertNothingSent();

        // The returned link actually works.
        parse_str(parse_url($res->json('inviteUrl'), PHP_URL_QUERY), $q);
        $this->assertSame('adi@example.com', $q['email']);
        $this->postJson('/api/auth/accept-invite', [
            'token' => $q['token'], 'email' => $q['email'],
            'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertOk();
        $this->assertSame('active', $tenant->fresh()->status);
    }

    public function test_invite_link_is_refused_for_active_tenants_and_other_owners(): void
    {
        $owner = User::factory()->owner()->create();
        Sanctum::actingAs($owner);

        $active = User::factory()->tenant()->create(['invited_by' => $owner->id]);
        $this->postJson("/api/tenants/{$active->id}/invite-link")->assertStatus(409);

        $someoneElses = User::factory()->invitedTenant()->create(['invited_by' => User::factory()->owner()->create()->id]);
        $this->postJson("/api/tenants/{$someoneElses->id}/invite-link")->assertStatus(403);
    }
}
