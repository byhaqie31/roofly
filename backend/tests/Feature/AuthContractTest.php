<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthContractTest extends TestCase
{
    use RefreshDatabase;

    public const AUTH_USER_KEYS = [
        'id', 'name', 'email', 'phone', 'role', 'permissions', 'isSuperAdmin',
        'hasPassword', 'avatarUrl', 'onboardedAt', 'purposes', 'checklistDismissedAt',
    ];

    public function test_me_returns_auth_user_shape(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());
        $res = $this->getJson('/api/auth/me')->assertOk();
        $this->assertSame(AuthContractTest::AUTH_USER_KEYS, array_keys($res->json()));
        $this->assertSame('owner', $res->json('role'));
    }

    public function test_login_returns_user_and_token(): void
    {
        User::factory()->owner()->create(['email' => 'a@b.my', 'password' => Hash::make('secret123')]);
        $res = $this->postJson('/api/auth/login', ['email' => 'a@b.my', 'password' => 'secret123'])->assertOk();
        $this->assertSame(['user', 'token'], array_keys($res->json()));
        $this->assertSame(AuthContractTest::AUTH_USER_KEYS, array_keys($res->json('user')));
    }

    public function test_register_creates_owner(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'name' => 'New Owner', 'email' => 'n@o.my', 'phone' => '+60 12',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertCreated();
        $this->assertSame('owner', $res->json('user.role'));
    }

    public function test_me_exposes_onboarding_fields_for_owner(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create([
            'purposes' => ['rental', 'own_stay'], 'onboarded_at' => now(),
        ]));
        $res = $this->getJson('/api/auth/me')->assertOk();
        $this->assertTrue($res->json('hasPassword'));
        $this->assertSame(['rental', 'own_stay'], $res->json('purposes'));
        $this->assertNotNull($res->json('onboardedAt'));
        $this->assertNull($res->json('checklistDismissedAt'));
    }

    public function test_me_returns_empty_purposes_for_tenant(): void
    {
        Sanctum::actingAs(User::factory()->tenant()->create());
        $res = $this->getJson('/api/auth/me')->assertOk();
        $this->assertSame([], $res->json('purposes'));
        $this->assertNull($res->json('onboardedAt'));
    }
}
