<?php
// backend/tests/Feature/Admin/AdminPermissionTest.php
namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
    }

    public function test_seeder_creates_all_keys_and_is_idempotent(): void
    {
        $this->assertSame(13, \Spatie\Permission\Models\Permission::count());
        $this->seed(AdminPermissionSeeder::class);
        $this->assertSame(13, \Spatie\Permission\Models\Permission::count());
        $this->assertContains('owners.view', AdminPermissions::operationsPreset());
        $this->assertNotContains('admins.manage', AdminPermissions::operationsPreset());
    }

    public function test_permissions_endpoint_requires_admins_manage(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/admin/permissions')->assertForbidden();
    }

    public function test_permissions_endpoint_returns_catalogue(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo(AdminPermissions::ADMINS_MANAGE);
        Sanctum::actingAs($admin);

        $res = $this->getJson('/api/admin/permissions')->assertOk();
        $this->assertSame(['permissions', 'preset'], array_keys($res->json()));
        $this->assertSame(['key', 'preset'], array_keys($res->json('permissions.0')));
        $this->assertCount(13, $res->json('permissions'));
    }

    public function test_super_admin_bypasses_every_check(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->getJson('/api/admin/permissions')->assertOk();
    }

    public function test_owner_and_tenant_are_blocked_from_admin_routes(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->getJson('/api/admin/permissions')->assertForbidden();
        Sanctum::actingAs(User::factory()->tenant()->create());
        $this->getJson('/api/admin/permissions')->assertForbidden();
    }
}
