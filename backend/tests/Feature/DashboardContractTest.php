<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        Sanctum::actingAs($this->owner);
    }

    public function test_dashboard_returns_the_expected_envelope_shape(): void
    {
        $res = $this->getJson('/api/dashboard')->assertOk();

        $this->assertSame(
            ['isEmpty', 'stats', 'incomeSeries', 'needsAttention'],
            array_keys($res->json())
        );
        $this->assertSame(
            ['monthlyIncome', 'occupancyPct', 'occupiedCount', 'unitCount', 'outstanding', 'outstandingCount', 'expiringCount'],
            array_keys($res->json('stats'))
        );
        // 12-month trailing series, oldest first, each {key, amount}.
        $this->assertCount(12, $res->json('incomeSeries'));
        $this->assertSame(['key', 'amount'], array_keys($res->json('incomeSeries.0')));
    }

    public function test_empty_owner_reports_is_empty(): void
    {
        $res = $this->getJson('/api/dashboard')->assertOk();
        $this->assertTrue($res->json('isEmpty'));
        $this->assertSame(0, $res->json('stats.occupancyPct'));
        $this->assertSame([], $res->json('needsAttention'));
    }

    public function test_stats_and_income_are_scoped_and_computed(): void
    {
        $property = Property::factory()->create(['owner_id' => $this->owner->id]);
        $occupied = Unit::factory()->create(['property_id' => $property->id, 'status' => 'occupied']);
        Unit::factory()->create(['property_id' => $property->id, 'status' => 'vacant']);

        $agreement = Agreement::factory()->create(['unit_id' => $occupied->id]);

        // Outstanding invoice (pending) with a late fee.
        Invoice::factory()->create([
            'agreement_id' => $agreement->id,
            'amount_cents' => 180000,
            'late_fee_cents' => 5000,
            'status' => 'pending',
        ]);

        // A paid invoice this month → counts toward monthly income.
        $paid = Invoice::factory()->create([
            'agreement_id' => $agreement->id,
            'amount_cents' => 180000,
            'status' => 'paid',
        ]);
        Payment::factory()->create([
            'invoice_id' => $paid->id,
            'amount_cents' => 180000,
            'status' => 'successful',
            'paid_at' => now(),
        ]);

        // Another owner's data must not leak in.
        $otherUnit = Unit::factory()->create();
        Invoice::factory()->create([
            'agreement_id' => Agreement::factory()->create(['unit_id' => $otherUnit->id])->id,
            'amount_cents' => 999999,
            'status' => 'pending',
        ]);

        $res = $this->getJson('/api/dashboard')->assertOk();

        $this->assertFalse($res->json('isEmpty'));
        $this->assertSame(2, $res->json('stats.unitCount'));
        $this->assertSame(1, $res->json('stats.occupiedCount'));
        $this->assertSame(50, $res->json('stats.occupancyPct'));
        $this->assertSame(185000, $res->json('stats.outstanding'));   // 180000 + 5000, mine only
        $this->assertSame(1, $res->json('stats.outstandingCount'));
        $this->assertSame(180000, $res->json('stats.monthlyIncome'));
        // Current month bucket (last in the series) holds the income.
        $this->assertSame(180000, $res->json('incomeSeries.11.amount'));
    }

    public function test_needs_attention_surfaces_overdue_and_reopened(): void
    {
        $property = Property::factory()->create(['owner_id' => $this->owner->id]);
        $unit = Unit::factory()->create(['property_id' => $property->id]);
        $agreement = Agreement::factory()->create(['unit_id' => $unit->id]);

        Invoice::factory()->create([
            'agreement_id' => $agreement->id,
            'invoice_number' => 'INV-OVERDUE',
            'status' => 'overdue',
        ]);
        Ticket::factory()->create(['unit_id' => $unit->id, 'status' => 'reopened']);

        $res = $this->getJson('/api/dashboard')->assertOk();
        $kinds = array_column($res->json('needsAttention'), 'kind');

        $this->assertContains('overdue', $kinds);
        $this->assertContains('ticket_reopened', $kinds);
        $this->assertSame(
            ['kind', 'title', 'meta', 'link'],
            array_keys($res->json('needsAttention.0'))
        );
    }

    public function test_tenant_is_blocked_from_the_owner_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->tenant()->create());
        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_non_rental_properties_are_excluded_from_occupancy_but_not_is_empty(): void
    {
        $home = \App\Models\Property::factory()->create(['owner_id' => $this->owner->id, 'purpose' => 'own_stay']);
        \App\Models\Unit::factory()->create(['property_id' => $home->id, 'status' => 'vacant']);

        $res = $this->getJson('/api/dashboard')->assertOk();
        $this->assertFalse($res->json('isEmpty'));
        $this->assertSame(0, $res->json('stats.unitCount'));

        $rental = \App\Models\Property::factory()->create(['owner_id' => $this->owner->id]);
        \App\Models\Unit::factory()->create(['property_id' => $rental->id, 'status' => 'occupied']);
        $res = $this->getJson('/api/dashboard')->assertOk();
        $this->assertSame(1, $res->json('stats.unitCount'));
        $this->assertSame(100, $res->json('stats.occupancyPct'));
    }
}
