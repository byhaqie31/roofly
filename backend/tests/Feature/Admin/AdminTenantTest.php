<?php
// backend/tests/Feature/Admin/AdminTenantTest.php
namespace Tests\Feature\Admin;

use App\Models\TenantInvite as TenantInviteModel;
use App\Models\User;
use App\Notifications\TenantInvite;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        $ops = User::factory()->admin()->create();
        $ops->givePermissionTo(AdminPermissions::TENANTS_VIEW);
        Sanctum::actingAs($ops);
    }

    public function test_list_search_and_filters(): void
    {
        $o1 = User::factory()->owner()->create();
        $o2 = User::factory()->owner()->create();
        User::factory()->tenant()->create(['name' => 'Aminah Yusof', 'invited_by' => $o1->id]);
        User::factory()->invitedTenant()->create(['name' => 'Lim Li Wei', 'invited_by' => $o2->id]);
        User::factory()->owner()->create(['name' => 'Aminah Owner']);

        $res = $this->getJson('/api/admin/tenants')->assertOk();
        $this->assertSame(2, $res->json('meta.total'));
        $this->assertSame(AdminResourcesTest::TENANT_KEYS, array_keys($res->json('data.0')));
        $this->assertSame(1, $this->getJson('/api/admin/tenants?q=aminah')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/tenants?status=invited')->json('meta.total'));
        $this->assertSame(1, $this->getJson("/api/admin/tenants?ownerId={$o2->id}")->json('meta.total'));
    }

    public function test_show_404s_for_non_tenant(): void
    {
        $tenant = User::factory()->tenant()->create();
        $this->getJson("/api/admin/tenants/{$tenant->id}")->assertOk()->assertJsonPath('id', $tenant->id);
        $this->getJson('/api/admin/tenants/' . User::factory()->owner()->create()->id)->assertNotFound();
    }

    public function test_resend_invite_only_for_invited_and_logs(): void
    {
        Notification::fake();
        $invited = User::factory()->invitedTenant()->create(['invited_at' => now()->subDays(9)]);
        $old = TenantInviteModel::create(['user_id' => $invited->id, 'token_hash' => hash('sha256', 'old'), 'expires_at' => now()->addDays(7)]);

        $this->postJson("/api/admin/tenants/{$invited->id}/resend-invite")->assertNoContent();

        $this->assertTrue($invited->fresh()->invited_at->isToday());
        $this->assertSame('tenant.invite_resent', Activity::inLog('admin')->latest('id')->first()->event);
        Notification::assertSentTo($invited, TenantInvite::class);
        $this->assertNotNull($old->fresh()->accepted_at); // the old link is voided — only the newest works
        $this->assertSame(1, TenantInviteModel::where('user_id', $invited->id)->whereNull('accepted_at')->count());

        $active = User::factory()->tenant()->create();
        $this->postJson("/api/admin/tenants/{$active->id}/resend-invite")->assertStatus(409);
    }
}
