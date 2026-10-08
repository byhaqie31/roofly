<?php
// backend/tests/Feature/Admin/AdminTenantTest.php
namespace Tests\Feature\Admin;

use App\Models\TenantInvite as TenantInviteModel;
use App\Http\Resources\Admin\AuditEntryResource;
use App\Models\User;
use App\Support\PrivacyMask;
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
        $o1 = User::factory()->owner()->create(['name' => 'Farid Kamal']);
        $o2 = User::factory()->owner()->create();
        User::factory()->tenant()->create(['name' => 'Aminah Yusof', 'email' => 'aminah.yusof@example.com', 'phone' => '+60 12-345 6789', 'invited_by' => $o1->id]);
        User::factory()->invitedTenant()->create(['name' => 'Lim Li Wei', 'invited_by' => $o2->id]);
        User::factory()->owner()->create(['name' => 'Aminah Owner']);

        $res = $this->getJson('/api/admin/tenants')->assertOk();
        $this->assertSame(2, $res->json('meta.total'));
        $this->assertSame(AdminResourcesTest::TENANT_KEYS, array_keys($res->json('data.0')));
        // Email, phone, owner or property name — never the tenant's (shortened) name.
        $this->assertSame(1, $this->getJson('/api/admin/tenants?q=yusof@example')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/tenants?q=345 6789')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/tenants?q=farid')->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/admin/tenants?q=Li Wei')->json('meta.total'));
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
        $entry = Activity::inLog('admin')->latest('id')->with(['causer', 'subject'])->first();
        $this->assertSame('tenant.invite_resent', $entry->event);
        // The audit trail masks the tenant like every other admin surface.
        $this->assertSame(PrivacyMask::name($invited->name), (new AuditEntryResource($entry))->resolve()['subjectName']);
        Notification::assertSentTo($invited, TenantInvite::class);
        $this->assertNotNull($old->fresh()->accepted_at); // the old link is voided — only the newest works
        $this->assertSame(1, TenantInviteModel::where('user_id', $invited->id)->whereNull('accepted_at')->count());

        $active = User::factory()->tenant()->create();
        $this->postJson("/api/admin/tenants/{$active->id}/resend-invite")->assertStatus(409);
    }
}
