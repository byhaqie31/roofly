<?php

namespace Tests\Feature\Admin;

use App\Http\Resources\Admin\AdminOwnerResource;
use App\Http\Resources\Admin\AdminPropertySummaryResource;
use App\Http\Resources\Admin\AdminTenantResource;
use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Support\OwnerCounts;
use App\Support\PlanCaps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The privacy line (spec § 6). If a future change adds a key here, this
 * test fails on purpose — widen the tier deliberately, never by accident.
 */
class AdminResourcesTest extends TestCase
{
    use RefreshDatabase;

    public const OWNER_KEYS = ['id', 'name', 'email', 'phone', 'businessName', 'planTier', 'unitsUsed', 'unitsCap', 'status', 'suspendedAt', 'suspensionReason', 'createdAt', 'lastActiveAt', 'counts'];
    public const COUNT_KEYS = ['properties', 'units', 'unitsOccupied', 'tenants', 'agreementsActive', 'agreementsExpiring30d', 'invoicesOverdue', 'ticketsOpen'];
    public const PROPERTY_KEYS = ['id', 'name', 'address', 'type', 'unitsTotal', 'unitsOccupied', 'createdAt'];
    public const TENANT_KEYS = ['id', 'name', 'email', 'phone', 'status', 'ownerId', 'ownerName', 'propertyName', 'unitLabel', 'invitedAt', 'acceptedAt', 'createdAt'];

    public function test_owner_resource_emits_exactly_the_summary_tier(): void
    {
        $owner = User::factory()->owner()->create(['plan_tier' => 'starter', 'bank_account_last4' => '4521']);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        Unit::factory()->create(['property_id' => $property->id, 'status' => 'occupied']);
        Unit::factory()->create(['property_id' => $property->id, 'status' => 'vacant']);

        $json = (new AdminOwnerResource($owner))->resolve();
        $this->assertSame(self::OWNER_KEYS, array_keys($json));
        $this->assertSame(self::COUNT_KEYS, array_keys($json['counts']));
        $this->assertSame('active', $json['status']);
        $this->assertSame(2, $json['unitsUsed']);
        $this->assertSame(5, $json['unitsCap']);
        $this->assertStringNotContainsString('4521', json_encode($json));
    }

    public function test_owner_resource_reports_suspension_and_unlimited_cap(): void
    {
        $owner = User::factory()->owner()->suspended('Late')->create(['plan_tier' => 'business']);
        $json = (new AdminOwnerResource($owner))->resolve();
        $this->assertSame('suspended', $json['status']);
        $this->assertSame('Late', $json['suspensionReason']);
        $this->assertNull($json['unitsCap']);
    }

    public function test_owner_counts(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $unit = Unit::factory()->create(['property_id' => $property->id, 'status' => 'occupied']);
        $tenant = User::factory()->tenant()->create(['invited_by' => $owner->id]);
        $agreement = Agreement::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'status' => 'active', 'end_date' => now()->addDays(10)->toDateString()]);
        Invoice::factory()->create(['agreement_id' => $agreement->id, 'status' => 'overdue']);
        Invoice::factory()->create(['agreement_id' => $agreement->id, 'status' => 'paid']);
        Ticket::factory()->create(['unit_id' => $unit->id, 'status' => 'new']);
        Ticket::factory()->create(['unit_id' => $unit->id, 'status' => 'resolved']);

        $this->assertSame([
            'properties' => 1, 'units' => 1, 'unitsOccupied' => 1, 'tenants' => 1,
            'agreementsActive' => 1, 'agreementsExpiring30d' => 1, 'invoicesOverdue' => 1, 'ticketsOpen' => 1,
        ], OwnerCounts::for($owner));
    }

    public function test_property_summary_resource(): void
    {
        $property = Property::factory()->create(['ownership' => ['purchasePrice' => 123], 'utilities' => ['tnb' => 'x']]);
        Unit::factory()->create(['property_id' => $property->id, 'status' => 'occupied']);
        $json = (new AdminPropertySummaryResource($property->load('units')))->resolve();
        $this->assertSame(self::PROPERTY_KEYS, array_keys($json));
        $this->assertSame(['line', 'postcode', 'city', 'state'], array_keys($json['address']));
        $this->assertSame(1, $json['unitsTotal']);
        $this->assertStringNotContainsString('purchasePrice', json_encode($json));
    }

    public function test_tenant_resource(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'Owner One']);
        $property = Property::factory()->create(['owner_id' => $owner->id, 'name' => 'Suria']);
        $unit = Unit::factory()->create(['property_id' => $property->id, 'label' => 'A-1']);
        $tenant = User::factory()->tenant()->create(['invited_by' => $owner->id, 'first_login_at' => now(), 'personal_info' => ['icNumber' => '880314-14-5687']]);
        Agreement::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'status' => 'active']);

        $json = (new AdminTenantResource($tenant->load(['inviter:id,name', 'agreements.unit.property:id,name,owner_id'])))->resolve();
        $this->assertSame(self::TENANT_KEYS, array_keys($json));
        $this->assertSame('Owner One', $json['ownerName']);
        $this->assertSame('Suria', $json['propertyName']);
        $this->assertSame('A-1', $json['unitLabel']);
        $this->assertNotNull($json['acceptedAt']);
        $this->assertStringNotContainsString('880314', json_encode($json));
    }

    public function test_plan_caps(): void
    {
        $this->assertSame(2, PlanCaps::unitsCap(\App\Enums\PlanTier::FREE));
        $this->assertNull(PlanCaps::unitsCap(\App\Enums\PlanTier::BUSINESS));
    }
}
