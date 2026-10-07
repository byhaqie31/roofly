<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AgreementReviewed;
use App\Notifications\AgreementSent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Spec: docs/superpowers/specs/2026-10-07-agreement-review-design.md */
class AgreementReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $tenant;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $property->id, 'user_id' => $this->owner->id]);
        $this->unit = Unit::factory()->create(['property_id' => $property->id]);
        $this->tenant = User::factory()->tenant()->create();
    }

    private function agreement(string $status = 'draft', array $attrs = []): Agreement
    {
        return Agreement::factory()->create(array_merge([
            'unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id, 'status' => $status,
            'start_date' => '2026-01-01', 'end_date' => '2027-12-31', 'rent_due_day' => 1,
        ], $attrs));
    }

    // ── Owner: send / withdraw ──────────────────────────────────────────────

    public function test_owner_sends_a_draft_to_the_tenant(): void
    {
        $a = $this->agreement('draft');
        Sanctum::actingAs($this->owner);

        $res = $this->postJson("/api/agreements/{$a->id}/send")->assertOk();

        $this->assertSame('pending_review', $res->json('status'));
        $this->assertNotNull($res->json('sentAt'));
        Notification::assertSentTo($this->tenant, AgreementSent::class, fn (AgreementSent $n) =>
            str_ends_with($n->url(), '/tenant/agreement'));
    }

    public function test_send_requires_draft_and_the_owner(): void
    {
        $active = $this->agreement('active');
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/agreements/{$active->id}/send")->assertStatus(409);

        $draft = $this->agreement('draft');
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->postJson("/api/agreements/{$draft->id}/send")->assertForbidden();
        Notification::assertNothingSent();
    }

    public function test_owner_withdraws_a_sent_agreement(): void
    {
        $a = $this->agreement('pending_review', ['sent_at' => now()]);
        Sanctum::actingAs($this->owner);

        $res = $this->postJson("/api/agreements/{$a->id}/withdraw")->assertOk();
        $this->assertSame('draft', $res->json('status'));
        $this->assertNull($res->json('sentAt'));

        $this->postJson("/api/agreements/{$a->id}/withdraw")->assertStatus(409); // already draft
    }

    // ── Tenant: accept / request changes ────────────────────────────────────

    public function test_tenant_agrees_and_the_owner_is_told(): void
    {
        $a = $this->agreement('pending_review', ['sent_at' => now()]);
        Sanctum::actingAs($this->tenant);

        $res = $this->postJson("/api/me/agreements/{$a->id}/accept")->assertOk();

        $this->assertSame('accepted', $res->json('status'));
        $this->assertNotNull($res->json('acceptedAt'));
        Notification::assertSentTo($this->owner, AgreementReviewed::class, fn (AgreementReviewed $n) =>
            $n->accepted === true && str_contains($n->url(), "/owner/agreements/{$a->id}"));
    }

    public function test_tenant_asks_for_changes_with_a_note(): void
    {
        $a = $this->agreement('pending_review', ['sent_at' => now()]);
        Sanctum::actingAs($this->tenant);

        $this->postJson("/api/me/agreements/{$a->id}/request-changes", [])->assertStatus(422)->assertJsonValidationErrors('note');

        $res = $this->postJson("/api/me/agreements/{$a->id}/request-changes", ['note' => 'Can we start on the 15th?'])->assertOk();

        $this->assertSame('draft', $res->json('status'));
        $this->assertSame('Can we start on the 15th?', $res->json('reviewNote'));
        $this->assertNotNull($res->json('changesRequestedAt'));
        $this->assertNull($res->json('sentAt'));
        Notification::assertSentTo($this->owner, AgreementReviewed::class, fn (AgreementReviewed $n) =>
            $n->accepted === false && $n->note === 'Can we start on the 15th?');
    }

    public function test_tenant_review_is_scoped_and_needs_a_sent_agreement(): void
    {
        $a = $this->agreement('pending_review', ['sent_at' => now()]);
        Sanctum::actingAs(User::factory()->tenant()->create());
        $this->postJson("/api/me/agreements/{$a->id}/accept")->assertForbidden();

        $draft = $this->agreement('draft');
        Sanctum::actingAs($this->tenant);
        $this->postJson("/api/me/agreements/{$draft->id}/accept")->assertStatus(409);
        $this->postJson("/api/me/agreements/{$draft->id}/request-changes", ['note' => 'x'])->assertStatus(409);
    }

    // ── Edits void the review ───────────────────────────────────────────────

    public function test_term_edits_while_sent_or_accepted_drop_back_to_draft(): void
    {
        Sanctum::actingAs($this->owner);

        $sent = $this->agreement('pending_review', ['sent_at' => now()]);
        $res = $this->putJson("/api/agreements/{$sent->id}", ['rentAmount' => 190000])->assertOk();
        $this->assertSame('draft', $res->json('status'));
        $this->assertNull($res->json('sentAt'));

        $accepted = $this->agreement('accepted', ['sent_at' => now()->subDay(), 'accepted_at' => now()]);
        $res = $this->putJson("/api/agreements/{$accepted->id}", ['endDate' => '2028-01-31'])->assertOk();
        $this->assertSame('draft', $res->json('status'));
        $this->assertNull($res->json('acceptedAt'));
    }

    public function test_an_edit_that_changes_nothing_keeps_the_review_state(): void
    {
        Sanctum::actingAs($this->owner);
        $sent = $this->agreement('pending_review', ['sent_at' => now(), 'rent_amount_cents' => 180000]);

        $res = $this->putJson("/api/agreements/{$sent->id}", ['rentAmount' => 180000])->assertOk();

        $this->assertSame('pending_review', $res->json('status'));
    }

    public function test_review_statuses_cannot_be_set_by_hand(): void
    {
        Sanctum::actingAs($this->owner);
        $a = $this->agreement('draft');
        $this->putJson("/api/agreements/{$a->id}", ['status' => 'pending_review'])->assertStatus(422);
        $this->putJson("/api/agreements/{$a->id}", ['status' => 'accepted'])->assertStatus(422);
        $this->postJson('/api/agreements', [
            'unitId' => $this->unit->id, 'tenantId' => $this->tenant->id,
            'startDate' => '2026-01-01', 'endDate' => '2027-12-31',
            'rentAmount' => 180000, 'depositAmount' => 360000, 'lateFee' => 5000,
            'rentDueDay' => 1, 'status' => 'accepted',
        ])->assertStatus(422);
    }

    public function test_activating_an_accepted_agreement_starts_invoices(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        Sanctum::actingAs($this->owner);
        $a = $this->agreement('accepted', ['sent_at' => now()->subDays(2), 'accepted_at' => now()->subDay()]);

        $res = $this->putJson("/api/agreements/{$a->id}", ['status' => 'active'])->assertOk();

        $this->assertSame('active', $res->json('status'));
        $this->assertNotNull($res->json('acceptedAt')); // activation keeps the acceptance record
        $this->assertSame(['2026-11-01'], Invoice::where('agreement_id', $a->id)->get()->map(fn ($i) => $i->due_date->toDateString())->all());
    }

    public function test_me_agreement_prefers_one_awaiting_review_over_an_old_expired_one(): void
    {
        $this->agreement('expired', ['start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $pending = $this->agreement('pending_review', ['sent_at' => now(), 'start_date' => '2026-11-01', 'end_date' => '2027-10-31']);
        Sanctum::actingAs($this->tenant);

        $this->assertSame($pending->id, $this->getJson('/api/me/agreement')->assertOk()->json('id'));
    }

    public function test_agreement_resource_carries_the_review_fields(): void
    {
        Sanctum::actingAs($this->owner);
        $a = $this->agreement('draft');
        $keys = array_keys($this->getJson("/api/agreements/{$a->id}")->assertOk()->json());
        foreach (['sentAt', 'acceptedAt', 'changesRequestedAt', 'reviewNote'] as $k) {
            $this->assertContains($k, $keys);
        }
    }
}
