<?php
// backend/tests/Feature/Admin/AdminDashboardTest.php
namespace Tests\Feature\Admin;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo(AdminPermissions::DASHBOARD_VIEW);
        Sanctum::actingAs($admin);
    }

    public function test_payload_shape_and_attention_kinds(): void
    {
        // over_cap: free plan (cap 2) with 3 units
        $over = User::factory()->owner()->create(['name' => 'Over Cap', 'plan_tier' => 'free']);
        $p = Property::factory()->create(['owner_id' => $over->id]);
        Unit::factory()->count(3)->create(['property_id' => $p->id, 'status' => 'occupied']);

        // overdue_3plus
        $due = User::factory()->owner()->create(['name' => 'Overdue']);
        $p2 = Property::factory()->create(['owner_id' => $due->id]);
        $u2 = Unit::factory()->create(['property_id' => $p2->id]);
        $a2 = Agreement::factory()->create(['unit_id' => $u2->id, 'tenant_id' => User::factory()->tenant()->create()->id, 'status' => 'active']);
        Invoice::factory()->count(3)->create(['agreement_id' => $a2->id, 'status' => 'overdue']);

        // invite_stale_7d
        $stale = User::factory()->owner()->create(['name' => 'Stale']);
        Property::factory()->create(['owner_id' => $stale->id]);
        User::factory()->invitedTenant()->create(['invited_by' => $stale->id, 'invited_at' => now()->subDays(9)]);

        // no_property_7d
        $empty = User::factory()->owner()->create(['name' => 'Empty', 'created_at' => now()->subDays(10)]);

        // suspended
        User::factory()->owner()->suspended()->create(['name' => 'Suspended']);

        $res = $this->getJson('/api/admin/dashboard')->assertOk();
        $this->assertSame(['tiles', 'series', 'attention'], array_keys($res->json()));
        $this->assertSame(['owners', 'tenants', 'properties', 'units', 'agreementsActive', 'agreementsExpiring30d', 'supportOpen'], array_keys($res->json('tiles')));
        $this->assertSame(['total', 'active', 'suspended'], array_keys($res->json('tiles.owners')));
        $this->assertSame(5, $res->json('tiles.owners.total'));
        $this->assertSame(1, $res->json('tiles.owners.suspended'));
        $this->assertSame(1, $res->json('tiles.tenants.invitedPending'));
        $this->assertSame(75, $res->json('tiles.units.occupiedPct')); // 3 occupied of 4
        $this->assertSame(0, $res->json('tiles.supportOpen'));

        $this->assertSame(['months', 'ownerSignups', 'invoicesIssued', 'invoicesPaid', 'inviteAcceptanceRate'], array_keys($res->json('series')));
        $this->assertCount(12, $res->json('series.months'));
        $this->assertCount(12, $res->json('series.ownerSignups'));
        $this->assertSame(now()->format('Y-m'), $res->json('series.months.11'));

        $kinds = collect($res->json('attention'))->pluck('kind')->all();
        foreach (['over_cap', 'overdue_3plus', 'invite_stale_7d', 'no_property_7d', 'suspended'] as $kind) {
            $this->assertContains($kind, $kinds, "missing attention kind {$kind}");
        }
        $this->assertSame(['kind', 'ownerId', 'ownerName', 'meta', 'link'], array_keys($res->json('attention.0')));
        $this->assertStringStartsWith('/admin/owners/', $res->json('attention.0.link'));
        $this->assertStringNotContainsString('amount', json_encode($res->json()));

        $noPropertyItem = collect($res->json('attention'))->firstWhere('kind', 'no_property_7d');
        $this->assertMatchesRegularExpression('/^\d+d$/', $noPropertyItem['meta']);
    }

    public function test_requires_dashboard_view(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }
}
