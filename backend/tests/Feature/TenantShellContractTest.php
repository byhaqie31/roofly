<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantShellContractTest extends TestCase
{
    use RefreshDatabase;

    private User $tenant;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $property->id, 'user_id' => $owner->id]);
        $this->unit = Unit::factory()->create(['property_id' => $property->id]);
        $this->tenant = User::factory()->tenant()->create();
        Sanctum::actingAs($this->tenant);
    }

    public function test_me_agreement_prefers_active_and_returns_envelope(): void
    {
        Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id, 'status' => 'expired', 'start_date' => '2025-01-01']);
        $active = Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id, 'status' => 'active']);

        $res = $this->getJson('/api/me/agreement?expand=unit,property,tenant')->assertOk();
        $this->assertSame(['agreement', 'unit', 'property', 'tenant', 'payoutAccount'], array_keys($res->json()));
        $this->assertSame($active->id, $res->json('agreement.id'));
    }

    public function test_me_agreement_falls_back_to_recent_non_draft_then_null(): void
    {
        // draft only → null (200, not 404)
        Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id, 'status' => 'draft']);
        $res = $this->getJson('/api/me/agreement');
        $res->assertOk();
        $this->assertSame('null', $res->getContent());

        $expired = Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id, 'status' => 'expired']);
        $this->assertSame($expired->id, $this->getJson('/api/me/agreement')->json('id'));
    }

    public function test_me_invoices_returns_envelopes_scoped_to_me(): void
    {
        $mine = Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id]);
        Invoice::factory()->create(['agreement_id' => $mine->id]);
        Invoice::factory()->create(); // someone else's

        $res = $this->getJson('/api/me/invoices?expand=agreement,unit,property,tenant,payments')->assertOk();
        $this->assertCount(1, $res->json());
        $this->assertSame(['invoice', 'agreement', 'unit', 'property', 'tenant', 'payments', 'payoutAccount'], array_keys($res->json()[0]));
    }

    public function test_me_pay_creates_successful_payment(): void
    {
        config(['app.online_payments' => true]); // simulated gateway is off by default (spec 2026-10-08)
        $agreement = Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['agreement_id' => $agreement->id, 'late_fee_cents' => 5000]);

        $res = $this->postJson("/api/me/invoices/{$invoice->id}/pay", ['method' => 'fpx'])->assertCreated();
        $this->assertSame(185000, $res->json('payment.amount')); // amount + lateFee
        $this->assertSame('paid', $res->json('invoice.status'));
    }

    public function test_me_ticket_flow_derives_unit_from_active_agreement(): void
    {
        Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id, 'status' => 'active']);

        $res = $this->postJson('/api/me/tickets', [
            'category' => 'electrical', 'priority' => 'urgent',
            'title' => 'No power', 'description' => 'Whole unit down.',
            'unitId' => 'ignored', 'reporterId' => 'ignored', 'reporterRole' => 'tenant',
        ])->assertCreated();
        $this->assertSame($this->unit->id, $res->json('unitId'));
        $this->assertSame('tenant', $res->json('reporterRole'));

        $list = $this->getJson('/api/me/tickets?expand=unit,property,reporter,comments')->assertOk();
        $this->assertSame(['ticket', 'unit', 'property', 'reporter', 'comments'], array_keys($list->json()[0]));
    }

    public function test_me_profile_roundtrip_camel_case(): void
    {
        $res = $this->getJson('/api/me/profile')->assertOk();
        $this->assertSame(['id', 'name', 'email', 'phone', 'personal', 'emergencyContact'], array_keys($res->json()));

        $patch = $this->patchJson('/api/me/profile', [
            'personal'         => ['icNumber' => '880314-14-5687', 'occupation' => 'Engineer'],
            'emergencyContact' => ['name' => 'Ali', 'phone' => '+60 13', 'relationship' => 'Brother'],
        ])->assertOk();
        $this->assertSame('Engineer', $patch->json('personal.occupation'));
        $this->assertSame('Ali', $this->tenant->fresh()->emergency_contact['name']);
    }
}
