<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        Sanctum::actingAs($this->owner);
    }

    public function test_invite_creates_invited_tenant(): void
    {
        $res = $this->postJson('/api/tenants/invite', [
            'name' => 'Aminah Binti Yusof', 'email' => 'aminah@x.my', 'phone' => '+60 12-345 6789',
        ])->assertCreated();

        // Envelope: the tenant plus the one-time plain invite link (spec 2026-10-07 § 4.3).
        $this->assertSame(['tenant', 'inviteUrl', 'inviteExpiresAt'], array_keys($res->json()));
        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'status', 'invitedAt', 'createdAt', 'personal', 'emergencyContact'],
            array_keys($res->json('tenant'))
        );
        $this->assertSame('invited', $res->json('tenant.status'));
        $this->assertNotNull($res->json('tenant.invitedAt'));
    }

    public function test_index_includes_invited_tenants_without_agreements(): void
    {
        // The old bug: invited tenants were invisible until they had an agreement.
        $this->postJson('/api/tenants/invite', [
            'name' => 'No Agreement Yet', 'email' => 'nay@x.my', 'phone' => '+60 1',
        ])->assertCreated();

        $res = $this->getJson('/api/tenants')->assertOk();
        $this->assertCount(1, $res->json());
        $this->assertSame('No Agreement Yet', $res->json('0.name'));
    }

    public function test_index_includes_agreement_tenants_and_excludes_strangers(): void
    {
        $unit = Unit::factory()->create([
            'property_id' => Property::factory()->create(['owner_id' => $this->owner->id])->id,
        ]);
        $agreementTenant = User::factory()->tenant()->create();
        Agreement::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $agreementTenant->id]);
        User::factory()->tenant()->create(); // stranger

        $res = $this->getJson('/api/tenants')->assertOk();
        $this->assertCount(1, $res->json());
        $this->assertSame($agreementTenant->id, $res->json('0.id'));
    }

    public function test_update_accepts_camel_case_patch(): void
    {
        $tenant = User::factory()->invitedTenant()->create(['invited_by' => $this->owner->id]);

        $res = $this->patchJson("/api/tenants/{$tenant->id}", [
            'status'           => 'notice_given',
            'personal'         => ['icNumber' => '880314-14-5687', 'monthlyIncome' => 650000],
            'emergencyContact' => ['name' => 'Ali', 'phone' => '+60 13', 'relationship' => 'Brother'],
        ])->assertOk();

        $this->assertSame('notice_given', $res->json('status'));
        $this->assertSame(650000, $res->json('personal.monthlyIncome'));
        $this->assertSame('Ali', $tenant->fresh()->emergency_contact['name']); // stored verbatim
    }
}
