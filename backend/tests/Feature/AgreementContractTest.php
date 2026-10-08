<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgreementContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $property->id, 'user_id' => $this->owner->id]);
        $this->unit = Unit::factory()->create(['property_id' => $property->id]);
        Sanctum::actingAs($this->owner);
    }

    public function test_plain_index_is_agreement_shape(): void
    {
        Agreement::factory()->create(['unit_id' => $this->unit->id]);
        $res = $this->getJson('/api/agreements')->assertOk();
        $this->assertSame(
            ['id', 'unitId', 'tenantId', 'startDate', 'endDate', 'rentAmount', 'depositAmount', 'lateFee', 'rentDueDay', 'status', 'sentAt', 'acceptedAt', 'changesRequestedAt', 'reviewNote', 'payoutAccountId', 'createdAt'],
            array_keys($res->json()[0])
        );
    }

    public function test_expand_returns_withrefs_envelopes(): void
    {
        Agreement::factory()->create(['unit_id' => $this->unit->id]);
        $res = $this->getJson('/api/agreements?expand=unit,property,tenant')->assertOk();
        $row = $res->json()[0];
        $this->assertSame(['agreement', 'unit', 'property', 'tenant', 'payoutAccount'], array_keys($row));
        $this->assertSame($this->unit->id, $row['unit']['id']);
        $this->assertArrayHasKey('coOwners', $row['property']);
        $this->assertArrayHasKey('status', $row['tenant']);
    }

    public function test_store_accepts_camel_case_input(): void
    {
        $tenant = User::factory()->tenant()->create();
        $res = $this->postJson('/api/agreements', [
            'unitId' => $this->unit->id, 'tenantId' => $tenant->id,
            'startDate' => '2026-08-01', 'endDate' => '2027-07-31',
            'rentAmount' => 180000, 'depositAmount' => 360000, 'lateFee' => 5000,
            'rentDueDay' => 1, 'status' => 'active',
        ])->assertCreated();
        $this->assertSame(180000, $res->json('rentAmount'));
        $this->assertSame('2026-08-01', $res->json('startDate'));
    }

    public function test_store_rejects_unit_of_other_owner(): void
    {
        $foreignUnit = Unit::factory()->create();
        $this->postJson('/api/agreements', [
            'unitId' => $foreignUnit->id, 'tenantId' => User::factory()->tenant()->create()->id,
            'startDate' => '2026-08-01', 'endDate' => '2027-07-31',
            'rentAmount' => 180000, 'depositAmount' => 360000, 'lateFee' => 0,
            'rentDueDay' => 1, 'status' => 'draft',
        ])->assertForbidden();
    }

    public function test_update_rejects_reassigning_to_foreign_unit(): void
    {
        $agreement = Agreement::factory()->create(['unit_id' => $this->unit->id]);
        $foreignUnit = Unit::factory()->create();

        $this->patchJson("/api/agreements/{$agreement->id}", ['unitId' => $foreignUnit->id])
            ->assertForbidden();

        $this->assertSame($this->unit->id, $agreement->fresh()->unit_id);
    }

    // ── Invoice generation side-effects (spec 2026-10-07 § 3.2) ─────────────

    private function agreementBody(array $overrides = []): array
    {
        return array_merge([
            'unitId' => $this->unit->id, 'tenantId' => User::factory()->tenant()->create()->id,
            'startDate' => '2026-01-01', 'endDate' => '2027-12-31',
            'rentAmount' => 180000, 'depositAmount' => 360000, 'lateFee' => 5000,
            'rentDueDay' => 1, 'status' => 'active',
        ], $overrides);
    }

    public function test_store_active_agreement_generates_the_next_invoice(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $res = $this->postJson('/api/agreements', $this->agreementBody())->assertCreated();

        $invoices = Invoice::where('agreement_id', $res->json('id'))->get();
        $this->assertCount(1, $invoices);
        $this->assertSame('2026-11-01', $invoices->first()->due_date->toDateString());
        $this->assertSame('pending', $invoices->first()->status->value);
    }

    public function test_store_draft_agreement_generates_nothing(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $res = $this->postJson('/api/agreements', $this->agreementBody(['status' => 'draft']))->assertCreated();
        $this->assertSame(0, Invoice::where('agreement_id', $res->json('id'))->count());
    }

    public function test_activating_an_agreement_generates_invoices(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $a = Agreement::factory()->create(['unit_id' => $this->unit->id, 'status' => 'draft', 'end_date' => '2027-12-31']);

        $this->putJson("/api/agreements/{$a->id}", ['status' => 'active'])->assertOk();

        $this->assertSame(['2026-11-01'], $a->invoices()->get()->map(fn (Invoice $i) => $i->due_date->toDateString())->all());
    }

    public function test_terminating_an_agreement_cancels_future_pending_invoices(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $a = Agreement::factory()->create(['unit_id' => $this->unit->id, 'end_date' => '2027-12-31']);
        Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-10-01', 'status' => 'pending']);
        Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-11-01', 'status' => 'pending']);

        $this->putJson("/api/agreements/{$a->id}", ['status' => 'terminated'])->assertOk();

        $this->assertSame(
            ['pending', 'cancelled'],
            $a->invoices()->orderBy('due_date')->get()->map(fn (Invoice $i) => $i->status->value)->all()
        );
    }
}
