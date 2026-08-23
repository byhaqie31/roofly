<?php
// backend/tests/Feature/Admin/AdminOwnerTest.php
namespace Tests\Feature\Admin;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\OwnerWarning;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminOwnerTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        $this->ops = User::factory()->admin()->create();
        $this->ops->givePermissionTo(AdminPermissions::operationsPreset());
        Sanctum::actingAs($this->ops);
    }

    public function test_list_is_paginated_searchable_and_filterable(): void
    {
        User::factory()->owner()->create(['name' => 'Alpha Aziz', 'plan_tier' => 'pro']);
        User::factory()->owner()->create(['name' => 'Beta Bakar', 'business_name' => 'Beta Homes']);
        User::factory()->owner()->suspended()->create(['name' => 'Gamma Ghani']);
        User::factory()->tenant()->create(['name' => 'Alpha Tenant']);

        $res = $this->getJson('/api/admin/owners')->assertOk();
        $this->assertSame(['data', 'meta'], array_keys($res->json()));
        $this->assertSame(['page', 'perPage', 'total', 'lastPage'], array_keys($res->json('meta')));
        $this->assertSame(3, $res->json('meta.total'));
        $this->assertSame(AdminResourcesTest::OWNER_KEYS, array_keys($res->json('data.0')));

        $this->assertSame(1, $this->getJson('/api/admin/owners?q=beta homes')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/owners?plan=pro')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/owners?status=suspended')->json('meta.total'));
        $this->assertSame(2, $this->getJson('/api/admin/owners?status=active')->json('meta.total'));
        $this->assertSame(2, $this->getJson('/api/admin/owners?perPage=2')->json('meta.lastPage'));
    }

    public function test_list_filters_over_cap_and_overdue(): void
    {
        $over = User::factory()->owner()->create(['plan_tier' => 'free']); // cap 2
        $p = Property::factory()->create(['owner_id' => $over->id]);
        Unit::factory()->count(3)->create(['property_id' => $p->id]);

        $due = User::factory()->owner()->create();
        $p2 = Property::factory()->create(['owner_id' => $due->id]);
        $u2 = Unit::factory()->create(['property_id' => $p2->id]);
        $a2 = Agreement::factory()->create(['unit_id' => $u2->id, 'tenant_id' => User::factory()->tenant()->create()->id]);
        Invoice::factory()->create(['agreement_id' => $a2->id, 'status' => 'overdue']);

        $this->assertSame([$over->id], array_column($this->getJson('/api/admin/owners?overCap=1')->json('data'), 'id'));
        $this->assertSame([$due->id], array_column($this->getJson('/api/admin/owners?overdue=1')->json('data'), 'id'));
    }

    public function test_show_properties_tenants_history(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        Unit::factory()->create(['property_id' => $property->id]);
        User::factory()->invitedTenant()->create(['invited_by' => $owner->id]);

        $this->getJson("/api/admin/owners/{$owner->id}")->assertOk()->assertJsonPath('id', $owner->id);
        $this->getJson('/api/admin/owners/' . User::factory()->tenant()->create()->id)->assertNotFound();

        $props = $this->getJson("/api/admin/owners/{$owner->id}/properties")->assertOk();
        $this->assertSame(AdminResourcesTest::PROPERTY_KEYS, array_keys($props->json()[0]));

        $tenants = $this->getJson("/api/admin/owners/{$owner->id}/tenants")->assertOk();
        $this->assertSame(AdminResourcesTest::TENANT_KEYS, array_keys($tenants->json()[0]));

        $this->postJson("/api/admin/owners/{$owner->id}/suspend", ['reason' => 'Unpaid subscription x2'])->assertOk();
        $history = $this->getJson("/api/admin/owners/{$owner->id}/history")->assertOk();
        $this->assertSame(['id', 'action', 'actorId', 'actorName', 'subjectType', 'subjectId', 'subjectName', 'before', 'after', 'reason', 'ip', 'createdAt'], array_keys($history->json()[0]));
        $this->assertSame('owner.suspended', $history->json('0.action'));
        $this->assertSame('owner.signup', $history->json('1.action'));
    }

    public function test_warn_sends_mail_notification_and_logs(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $suspendOn = $this->futureDate();

        $this->postJson("/api/admin/owners/{$owner->id}/warn", [
            'template' => 'payment_overdue', 'suspendOn' => $suspendOn, 'extraLine' => 'Reply to this email if you need help.',
        ])->assertNoContent();

        Notification::assertSentTo($owner, OwnerWarning::class, fn ($n, $channels) => $channels === ['mail']);
        $row = Activity::inLog('admin')->where('event', 'owner.warned')->first();
        $this->assertSame($owner->id, $row->subject_id);
        $this->assertSame('payment_overdue', $row->properties['after']['template']);
        $this->assertStringContainsString($suspendOn, $row->properties['after']['text']);

        $this->postJson("/api/admin/owners/{$owner->id}/warn", ['template' => 'nope', 'suspendOn' => $suspendOn])->assertUnprocessable();
    }

    public function test_suspend_and_unsuspend_with_audit_and_conflicts(): void
    {
        $owner = User::factory()->owner()->create();

        $this->postJson("/api/admin/owners/{$owner->id}/suspend", ['reason' => 'short'])->assertUnprocessable();

        $res = $this->postJson("/api/admin/owners/{$owner->id}/suspend", ['reason' => 'Unpaid subscription for two months'])->assertOk();
        $this->assertSame('suspended', $res->json('status'));
        $this->assertTrue($owner->fresh()->isSuspended());
        $this->assertSame('Unpaid subscription for two months', Activity::inLog('admin')->where('event', 'owner.suspended')->first()->properties['reason']);

        $this->postJson("/api/admin/owners/{$owner->id}/suspend", ['reason' => 'Unpaid subscription for two months'])->assertStatus(409);

        $res = $this->postJson("/api/admin/owners/{$owner->id}/unsuspend")->assertOk();
        $this->assertSame('active', $res->json('status'));
        $this->assertNull($owner->fresh()->suspension_reason);
        $this->assertSame(1, Activity::inLog('admin')->where('event', 'owner.unsuspended')->count());

        $this->postJson("/api/admin/owners/{$owner->id}/unsuspend")->assertStatus(409);
    }

    public function test_permissions_are_enforced_per_route(): void
    {
        $viewer = User::factory()->admin()->create();
        $viewer->givePermissionTo(AdminPermissions::OWNERS_VIEW);
        Sanctum::actingAs($viewer);
        $owner = User::factory()->owner()->create();

        $this->getJson('/api/admin/owners')->assertOk();
        $this->postJson("/api/admin/owners/{$owner->id}/warn", ['template' => 'payment_overdue', 'suspendOn' => $this->futureDate()])->assertForbidden();
        $this->postJson("/api/admin/owners/{$owner->id}/suspend", ['reason' => 'Unpaid subscription for two months'])->assertForbidden();
    }

    private function futureDate(): string
    {
        return now()->addDays(7)->toDateString();
    }
}
