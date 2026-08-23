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

    public function test_me_returns_auth_user_shape(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());
        $res = $this->getJson('/api/auth/me')->assertOk();
        $this->assertSame(['id', 'name', 'email', 'phone', 'role', 'permissions', 'isSuperAdmin'], array_keys($res->json()));
        $this->assertSame('owner', $res->json('role'));
    }

    public function test_login_returns_user_and_token(): void
    {
        User::factory()->owner()->create(['email' => 'a@b.my', 'password' => Hash::make('secret123')]);
        $res = $this->postJson('/api/auth/login', ['email' => 'a@b.my', 'password' => 'secret123'])->assertOk();
        $this->assertSame(['user', 'token'], array_keys($res->json()));
        $this->assertSame(['id', 'name', 'email', 'phone', 'role', 'permissions', 'isSuperAdmin'], array_keys($res->json('user')));
    }

    public function test_register_creates_owner(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'name' => 'New Owner', 'email' => 'n@o.my', 'phone' => '+60 12',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertCreated();
        $this->assertSame('owner', $res->json('user.role'));
    }
}
