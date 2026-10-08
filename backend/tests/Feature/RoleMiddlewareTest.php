<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_reach_owner_routes(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->getJson('/api/properties')->assertOk();
    }

    public function test_tenant_is_blocked_from_owner_routes(): void
    {
        Sanctum::actingAs(User::factory()->tenant()->create());
        $this->getJson('/api/properties')->assertForbidden();
    }

    public function test_owner_is_blocked_from_tenant_routes(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->getJson('/api/me/invoices')->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/properties')->assertUnauthorized();
    }
}
