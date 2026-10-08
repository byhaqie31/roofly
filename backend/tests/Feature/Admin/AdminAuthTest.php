<?php
// backend/tests/Feature/Admin/AdminAuthTest.php
namespace Tests\Feature\Admin;

use App\Models\AdminInvite;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = [
        'id', 'name', 'email', 'phone', 'role', 'permissions', 'isSuperAdmin',
        'hasPassword', 'avatarUrl', 'onboardedAt', 'purposes', 'checklistDismissedAt',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
    }

    public function test_admin_login_accepts_admin_and_logs(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'ops@x.my', 'password' => Hash::make('secret123')]);
        $admin->givePermissionTo(AdminPermissions::OWNERS_VIEW);

        $res = $this->postJson('/api/admin/auth/login', ['email' => 'ops@x.my', 'password' => 'secret123'])->assertOk();
        $this->assertSame(['user'], array_keys($res->json()));
        $this->assertSame(self::KEYS, array_keys($res->json('user')));
        $this->assertSame(['owners.view'], $res->json('user.permissions'));
        $this->assertFalse($res->json('user.isSuperAdmin'));
        $this->assertSame('admin.login', Activity::inLog('admin')->latest('id')->first()->event);
        $this->assertNotNull($admin->fresh()->first_login_at);
    }

    public function test_super_admin_login_returns_full_catalogue(): void
    {
        User::factory()->superAdmin()->create(['email' => 'su@x.my', 'password' => Hash::make('secret123')]);
        $res = $this->postJson('/api/admin/auth/login', ['email' => 'su@x.my', 'password' => 'secret123'])->assertOk();
        $this->assertTrue($res->json('user.isSuperAdmin'));
        $this->assertSame(AdminPermissions::keys(), $res->json('user.permissions'));
    }

    public function test_admin_login_rejects_owner_disabled_admin_and_bad_password(): void
    {
        User::factory()->owner()->create(['email' => 'o@x.my', 'password' => Hash::make('secret123')]);
        $this->postJson('/api/admin/auth/login', ['email' => 'o@x.my', 'password' => 'secret123'])->assertUnauthorized();

        User::factory()->admin()->create(['email' => 'd@x.my', 'password' => Hash::make('secret123'), 'disabled_at' => now()]);
        $this->postJson('/api/admin/auth/login', ['email' => 'd@x.my', 'password' => 'secret123'])->assertUnauthorized();

        User::factory()->admin()->create(['email' => 'a@x.my', 'password' => Hash::make('secret123')]);
        $this->postJson('/api/admin/auth/login', ['email' => 'a@x.my', 'password' => 'wrong'])->assertUnauthorized();
    }

    public function test_customer_login_rejects_admin(): void
    {
        User::factory()->admin()->create(['email' => 'a@x.my', 'password' => Hash::make('secret123')]);
        $this->postJson('/api/auth/login', ['email' => 'a@x.my', 'password' => 'secret123'])->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_me_includes_permissions_for_every_role(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());
        $res = $this->getJson('/api/auth/me')->assertOk();
        $this->assertSame(self::KEYS, array_keys($res->json()));
        $this->assertSame([], $res->json('permissions'));
        $this->assertFalse($res->json('isSuperAdmin'));
    }

    public function test_accept_invite_sets_password_and_logs_in(): void
    {
        $admin = User::factory()->admin()->create(['password' => null]);
        AdminInvite::create(['user_id' => $admin->id, 'token_hash' => hash('sha256', 'plain-token'), 'expires_at' => now()->addDays(7)]);

        $res = $this->postJson('/api/admin/auth/accept-invite', [
            'token' => 'plain-token', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertOk();
        $this->assertSame(self::KEYS, array_keys($res->json('user')));
        $this->assertTrue(Hash::check('secret123', $admin->fresh()->password));
        $this->assertNotNull(AdminInvite::first()->accepted_at);
        $this->assertSame('admin.invite_accepted', Activity::inLog('admin')->latest('id')->first()->event);

        // Token is single-use.
        $this->postJson('/api/admin/auth/accept-invite', [
            'token' => 'plain-token', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertUnprocessable();
    }

    public function test_accept_invite_rejects_expired_token(): void
    {
        $admin = User::factory()->admin()->create(['password' => null]);
        AdminInvite::create(['user_id' => $admin->id, 'token_hash' => hash('sha256', 'old'), 'expires_at' => now()->subDay()]);
        $this->postJson('/api/admin/auth/accept-invite', ['token' => 'old', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertUnprocessable();
    }

    public function test_touch_last_active_is_throttled(): void
    {
        $owner = User::factory()->owner()->create(['last_active_at' => null]);
        Sanctum::actingAs($owner);
        $this->getJson('/api/auth/me')->assertOk();
        $first = $owner->fresh()->last_active_at;
        $this->assertNotNull($first);

        $this->travel(5)->minutes();
        $this->getJson('/api/auth/me')->assertOk();
        $this->assertTrue($owner->fresh()->last_active_at->equalTo($first));

        $this->travel(6)->minutes();
        $this->getJson('/api/auth/me')->assertOk();
        $this->assertTrue($owner->fresh()->last_active_at->greaterThan($first));
    }
}
