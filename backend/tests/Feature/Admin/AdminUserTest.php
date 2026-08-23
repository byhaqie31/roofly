<?php
// backend/tests/Feature/Admin/AdminUserTest.php
namespace Tests\Feature\Admin;

use App\Models\AdminInvite;
use App\Models\User;
use App\Notifications\AdminInvite as AdminInviteNotification;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = ['id', 'name', 'email', 'permissions', 'isSuperAdmin', 'status', 'lastActiveAt', 'createdAt'];

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        Notification::fake();
        $this->super = User::factory()->superAdmin()->create(['first_login_at' => now()]);
        Sanctum::actingAs($this->super);
    }

    public function test_list_returns_admins_with_status(): void
    {
        $invited = User::factory()->admin()->create(['password' => null]);
        User::factory()->admin()->create(['first_login_at' => now(), 'disabled_at' => now()]);

        $res = $this->getJson('/api/admin/admins')->assertOk();
        $this->assertCount(3, $res->json());
        $this->assertSame(self::KEYS, array_keys($res->json()[0]));
        $byId = collect($res->json())->keyBy('id');
        $this->assertSame('invited', $byId[$invited->id]['status']);
        $this->assertSame('active', $byId[$this->super->id]['status']);
        $this->assertContains('disabled', $byId->pluck('status'));
    }

    public function test_create_sends_invite_assigns_permissions_and_logs(): void
    {
        $res = $this->postJson('/api/admin/admins', [
            'name' => 'Ops One', 'email' => 'ops1@roofly.my', 'permissions' => AdminPermissions::operationsPreset(),
        ])->assertCreated();

        $this->assertSame(self::KEYS, array_keys($res->json()));
        $this->assertSame('invited', $res->json('status'));
        $this->assertSame(AdminPermissions::operationsPreset(), $res->json('permissions'));

        $user = User::where('email', 'ops1@roofly.my')->first();
        $this->assertSame('admin', $user->role->value);
        $this->assertNull($user->password);
        $this->assertCount(1, $user->adminInvites);
        Notification::assertSentTo($user, AdminInviteNotification::class);
        $this->assertSame('admin.invite_sent', Activity::inLog('admin')->latest('id')->first()->event);

        $this->postJson('/api/admin/admins', ['name' => 'X', 'email' => 'ops1@roofly.my', 'permissions' => []])->assertUnprocessable();
        $this->postJson('/api/admin/admins', ['name' => 'X', 'email' => 'y@roofly.my', 'permissions' => ['not.a.key']])->assertUnprocessable();
    }

    public function test_only_super_admin_can_grant_super_admin(): void
    {
        $manager = User::factory()->admin()->create(['first_login_at' => now()]);
        $manager->givePermissionTo(AdminPermissions::ADMINS_MANAGE);
        Sanctum::actingAs($manager);

        $this->postJson('/api/admin/admins', ['name' => 'S', 'email' => 's@roofly.my', 'permissions' => [], 'isSuperAdmin' => true])->assertForbidden();
        $this->postJson('/api/admin/admins', ['name' => 'S', 'email' => 's@roofly.my', 'permissions' => []])->assertCreated();
    }

    public function test_update_permissions_disable_enable_with_audit(): void
    {
        $ops = User::factory()->admin()->create(['first_login_at' => now()]);
        $ops->givePermissionTo(AdminPermissions::OWNERS_VIEW);

        $res = $this->patchJson("/api/admin/admins/{$ops->id}", ['permissions' => [AdminPermissions::OWNERS_VIEW, AdminPermissions::AUDIT_VIEW]])->assertOk();
        $this->assertSame([AdminPermissions::OWNERS_VIEW, AdminPermissions::AUDIT_VIEW], $res->json('permissions'));
        $log = Activity::inLog('admin')->where('event', 'admin.permissions_changed')->first();
        $this->assertSame(['owners.view'], $log->properties['before']['permissions']);

        $this->patchJson("/api/admin/admins/{$ops->id}", ['disabled' => true])->assertOk()->assertJsonPath('status', 'disabled');
        $this->assertSame(1, Activity::inLog('admin')->where('event', 'admin.disabled')->count());

        $this->patchJson("/api/admin/admins/{$ops->id}", ['disabled' => false])->assertOk()->assertJsonPath('status', 'active');
        $this->assertSame(1, Activity::inLog('admin')->where('event', 'admin.enabled')->count());
    }

    public function test_last_super_admin_cannot_be_disabled_or_demoted_and_self_disable_is_blocked(): void
    {
        $this->patchJson("/api/admin/admins/{$this->super->id}", ['disabled' => true])->assertUnprocessable();
        $this->patchJson("/api/admin/admins/{$this->super->id}", ['isSuperAdmin' => false])->assertUnprocessable();

        $second = User::factory()->superAdmin()->create(['first_login_at' => now()]);
        $this->patchJson("/api/admin/admins/{$second->id}", ['isSuperAdmin' => false])->assertOk()->assertJsonPath('isSuperAdmin', false);

        // Self-disable is blocked even with another super-admin around.
        User::factory()->superAdmin()->create(['first_login_at' => now()]);
        $this->patchJson("/api/admin/admins/{$this->super->id}", ['disabled' => true])->assertUnprocessable();
    }

    public function test_resend_invite(): void
    {
        $invited = User::factory()->admin()->create(['password' => null]);
        AdminInvite::create(['user_id' => $invited->id, 'token_hash' => hash('sha256', 'old'), 'expires_at' => now()->addDay()]);

        $this->postJson("/api/admin/admins/{$invited->id}/resend-invite")->assertNoContent();
        $this->assertCount(2, $invited->fresh()->adminInvites);
        $this->assertNotNull(AdminInvite::where('token_hash', hash('sha256', 'old'))->first()->accepted_at, 'old token is voided');
        Notification::assertSentTo($invited, AdminInviteNotification::class);

        $this->postJson("/api/admin/admins/{$this->super->id}/resend-invite")->assertStatus(409);
    }

    public function test_routes_need_admins_manage(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/admin/admins')->assertForbidden();
    }
}
