<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_always_200_and_only_notifies_existing_non_admins(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create(['email' => 'o@example.com']);
        User::factory()->superAdmin()->create(['email' => 'admin@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();
        $this->postJson('/api/auth/forgot-password', ['email' => 'admin@example.com'])->assertOk();
        $this->postJson('/api/auth/forgot-password', ['email' => 'o@example.com'])->assertOk();

        Notification::assertSentTo($owner, ResetPassword::class, function (ResetPassword $n) use ($owner) {
            $url = $n->url($owner);
            return str_starts_with($url, rtrim(config('app.frontend_url'), '/') . '/auth/reset-password?token=')
                && str_contains($url, 'email=o%40example.com');
        });
        Notification::assertCount(1);
    }

    public function test_reset_sets_password_and_logs_in(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'o@example.com']);
        $token = Password::createToken($owner);

        $res = $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'o@example.com',
            'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertOk();

        $this->assertSame(['user', 'token'], array_keys($res->json()));
        $this->assertSame($owner->id, $res->json('user.id'));
        $this->assertTrue(Hash::check('newsecret1', $owner->fresh()->password));
    }

    public function test_reset_gives_google_only_account_a_password(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'g@example.com', 'password' => null, 'google_id' => 'g-1']);
        $token = Password::createToken($owner);
        $res = $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'g@example.com',
            'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertOk();
        $this->assertTrue($res->json('user.hasPassword'));
        $this->assertTrue(Hash::check('newsecret1', $owner->fresh()->password));
    }

    public function test_reset_with_bad_token_is_422(): void
    {
        User::factory()->owner()->create(['email' => 'o@example.com']);
        $this->postJson('/api/auth/reset-password', [
            'token' => 'nope', 'email' => 'o@example.com',
            'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertStatus(422)->assertJsonValidationErrors(['email'])->assertJsonMissingPath('token');
        $this->assertGuest();
    }

    public function test_reset_validates_password_rules(): void
    {
        $this->postJson('/api/auth/reset-password', ['token' => 't', 'email' => 'x@y.my', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_reset_admin_holding_valid_token_cannot_complete_reset(): void
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'a@example.com']);
        $token = Password::createToken($admin);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'a@example.com',
            'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertStatus(422)->assertJsonValidationErrors(['email'])->assertJsonMissingPath('token');

        $this->assertFalse(Hash::check('newsecret1', $admin->fresh()->password));
        $this->assertGuest();
    }
}
