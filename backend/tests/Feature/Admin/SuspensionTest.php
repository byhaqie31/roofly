<?php
// backend/tests/Feature/Admin/SuspensionTest.php
namespace Tests\Feature\Admin;

use App\Models\Agreement;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_owner_gets_403_account_suspended_on_owner_routes(): void
    {
        $owner = User::factory()->owner()->suspended('Unpaid subscription')->create();
        Sanctum::actingAs($owner);

        $res = $this->getJson('/api/properties')->assertForbidden();
        $this->assertSame('account_suspended', $res->json('code'));
        $this->assertSame(['code', 'message'], array_keys($res->json()));
        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_suspended_owner_can_still_probe_me_and_logout(): void
    {
        Sanctum::actingAs(User::factory()->owner()->suspended()->create());
        $this->getJson('/api/auth/me')->assertOk();
    }

    public function test_suspended_owners_tenant_is_unaffected(): void
    {
        $owner = User::factory()->owner()->suspended()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $unit = Unit::factory()->create(['property_id' => $property->id]);
        $tenant = User::factory()->tenant()->create(['invited_by' => $owner->id]);
        Agreement::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'status' => 'active']);

        Sanctum::actingAs($tenant);
        $this->getJson('/api/me/agreement')->assertOk();
    }

    public function test_unsuspended_owner_regains_access(): void
    {
        $owner = User::factory()->owner()->suspended()->create();
        Sanctum::actingAs($owner);
        $this->getJson('/api/properties')->assertForbidden();
        $owner->update(['suspended_at' => null, 'suspension_reason' => null]);
        $this->getJson('/api/properties')->assertOk();
    }
}
