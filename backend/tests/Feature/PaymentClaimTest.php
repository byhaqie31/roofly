<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PayoutAccount;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\PaymentClaimRejected;
use App\Notifications\PaymentClaimSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Spec: docs/superpowers/specs/2026-10-08-payout-accounts-duitnow-design.md § 3.3, § 4. */
class PaymentClaimTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $tenant;
    private PayoutAccount $account;
    private Agreement $agreement;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->owner = User::factory()->owner()->create();
        $this->account = PayoutAccount::factory()->default()->create(['owner_id' => $this->owner->id]);
        $property = Property::factory()->create(['owner_id' => $this->owner->id]);
        $unit = Unit::factory()->create(['property_id' => $property->id]);
        $this->tenant = User::factory()->tenant()->create(['name' => 'Aminah Yusof']);
        $this->agreement = Agreement::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $this->tenant->id]);
        $this->invoice = Invoice::factory()->create([
            'agreement_id' => $this->agreement->id, 'invoice_number' => 'INV-0042',
            'status' => 'overdue', 'late_fee_cents' => 5000, 'due_date' => '2026-07-01',
        ]);
    }

    private function claim(array $body = [], ?Invoice $invoice = null)
    {
        Sanctum::actingAs($this->tenant);

        return $this->postJson('/api/me/invoices/' . ($invoice ?? $this->invoice)->id . '/claim', array_merge([
            'reference' => 'DN2610081234',
            'paidAt'    => '2026-06-30',
            'note'      => 'Paid via Maybank2u',
        ], $body));
    }

    private function pendingClaim(): Payment
    {
        return Payment::factory()->create([
            'invoice_id' => $this->invoice->id, 'method' => 'transfer', 'status' => 'pending',
            'reference' => 'DN1', 'paid_at' => '2026-06-30', 'payout_account_id' => $this->account->id,
            'amount_cents' => 185000,
        ]);
    }

    // ── Tenant claims ───────────────────────────────────────────────────────

    public function test_tenant_claim_creates_pending_transfer_and_emails_owner(): void
    {
        $res = $this->claim()->assertCreated();

        $this->assertSame(['payment', 'invoice'], array_keys($res->json()));
        $this->assertSame('pending', $res->json('payment.status'));
        $this->assertSame('transfer', $res->json('payment.method'));
        $this->assertSame(185000, $res->json('payment.amount')); // amount + late fee at claim time
        $this->assertSame('DN2610081234', $res->json('payment.reference'));
        $this->assertSame('Paid via Maybank2u', $res->json('payment.note'));
        $this->assertSame($this->account->id, $res->json('payment.payoutAccountId'));
        $this->assertStringStartsWith('2026-06-30', $res->json('payment.paidAt'));
        $this->assertNull($res->json('payment.confirmedAt'));
        $this->assertSame('overdue', $res->json('invoice.status')); // unchanged — "awaiting" is derived

        Notification::assertSentTo($this->owner, PaymentClaimSubmitted::class, fn (PaymentClaimSubmitted $n) =>
            $n->invoiceNumber === 'INV-0042'
            && $n->tenantName === 'Aminah Yusof'
            && str_ends_with($n->url(), '/owner/payments?status=awaiting'));
    }

    public function test_claim_uses_the_agreement_override_account(): void
    {
        $override = PayoutAccount::factory()->create(['owner_id' => $this->owner->id]);
        $this->agreement->update(['payout_account_id' => $override->id]);

        $this->assertSame($override->id, $this->claim()->assertCreated()->json('payment.payoutAccountId'));
    }

    public function test_no_email_when_owner_turned_payment_received_off(): void
    {
        $this->owner->update(['notification_preferences' => [
            'events' => ['payment_received' => false], 'channels' => ['email' => true],
        ]]);

        $this->claim()->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_claim_validation(): void
    {
        $this->claim(['reference' => '', 'paidAt' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['reference', 'paidAt']);
        $this->claim(['paidAt' => now('Asia/Kuala_Lumpur')->addDays(2)->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors(['paidAt']);
        $this->claim(['reference' => str_repeat('x', 101), 'note' => str_repeat('y', 501)])
            ->assertUnprocessable()->assertJsonValidationErrors(['reference', 'note']);
    }

    public function test_claim_on_someone_elses_invoice_is_forbidden(): void
    {
        $this->claim([], Invoice::factory()->create())->assertForbidden();
    }

    public function test_claim_requires_pending_or_overdue_invoice(): void
    {
        $this->invoice->update(['status' => 'paid']);
        $this->claim()->assertUnprocessable();

        $this->invoice->update(['status' => 'cancelled']);
        $this->claim()->assertUnprocessable();
    }

    public function test_second_claim_while_one_is_pending_is_a_conflict(): void
    {
        $this->claim()->assertCreated();

        $this->claim(['reference' => 'AGAIN'])->assertStatus(409)->assertJson(['code' => 'claim_pending']);
        $this->assertSame(1, Payment::where('invoice_id', $this->invoice->id)->count());
    }

    public function test_claim_without_any_payout_account_is_rejected_with_a_code(): void
    {
        $this->account->delete();

        $this->claim()->assertUnprocessable()->assertJson(['code' => 'no_payout_account']);
        $this->assertSame(0, Payment::count());
    }

    public function test_tenant_can_claim_again_after_a_rejection(): void
    {
        Payment::factory()->create(['invoice_id' => $this->invoice->id, 'method' => 'transfer', 'status' => 'failed', 'rejection_reason' => 'Not received']);

        $this->claim()->assertCreated();
    }

    // ── Owner confirms / rejects ────────────────────────────────────────────

    public function test_owner_confirms_claim_and_invoice_is_paid(): void
    {
        $payment = $this->pendingClaim();
        Sanctum::actingAs($this->owner);

        $res = $this->postJson("/api/payments/{$payment->id}/confirm", ['paidAt' => '2026-07-02'])->assertOk();

        $this->assertSame(['payment', 'invoice'], array_keys($res->json()));
        $this->assertSame('successful', $res->json('payment.status'));
        $this->assertNotNull($res->json('payment.confirmedAt'));
        $this->assertStringStartsWith('2026-07-02', $res->json('payment.paidAt'));
        $this->assertSame('paid', $res->json('invoice.status'));
    }

    public function test_confirm_keeps_claimed_date_when_none_given(): void
    {
        $payment = $this->pendingClaim();
        Sanctum::actingAs($this->owner);

        $res = $this->postJson("/api/payments/{$payment->id}/confirm")->assertOk();

        $this->assertStringStartsWith('2026-06-30', $res->json('payment.paidAt'));
    }

    public function test_owner_rejects_claim_with_reason_and_tenant_is_emailed(): void
    {
        $payment = $this->pendingClaim();
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/payments/{$payment->id}/reject", ['reason' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->postJson("/api/payments/{$payment->id}/reject", ['reason' => str_repeat('x', 301)])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $res = $this->postJson("/api/payments/{$payment->id}/reject", ['reason' => 'Nothing arrived in Maybank'])->assertOk();

        $this->assertSame('failed', $res->json('payment.status'));
        $this->assertSame('Nothing arrived in Maybank', $res->json('payment.rejectionReason'));
        $this->assertSame('overdue', $res->json('invoice.status')); // untouched
        Notification::assertSentTo($this->tenant, PaymentClaimRejected::class, fn (PaymentClaimRejected $n) =>
            $n->reason === 'Nothing arrived in Maybank' && str_ends_with($n->url(), '/tenant/payments'));
    }

    public function test_answered_claims_are_a_conflict(): void
    {
        $payment = $this->pendingClaim();
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/payments/{$payment->id}/confirm")->assertOk();

        $this->postJson("/api/payments/{$payment->id}/confirm")->assertStatus(409);
        $this->postJson("/api/payments/{$payment->id}/reject", ['reason' => 'x'])->assertStatus(409);
    }

    public function test_another_owner_cannot_answer_the_claim(): void
    {
        $payment = $this->pendingClaim();
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->postJson("/api/payments/{$payment->id}/confirm")->assertForbidden();
        $this->postJson("/api/payments/{$payment->id}/reject", ['reason' => 'x'])->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->status->value);
    }

    public function test_tenant_cannot_confirm_their_own_claim(): void
    {
        $payment = $this->pendingClaim();
        Sanctum::actingAs($this->tenant);

        $this->postJson("/api/payments/{$payment->id}/confirm")->assertForbidden();
    }

    public function test_owner_invoice_list_filters_awaiting(): void
    {
        $this->pendingClaim();
        Invoice::factory()->create(['agreement_id' => $this->agreement->id, 'due_date' => '2026-08-01']);
        Sanctum::actingAs($this->owner);

        $res = $this->getJson('/api/invoices?status=awaiting&expand=1')->assertOk();

        $this->assertSame([$this->invoice->id], array_column(array_column($res->json(), 'invoice'), 'id'));
        $this->assertSame($this->account->id, $res->json('0.payoutAccount.id'));
    }

    // ── Simulated gateway gate ──────────────────────────────────────────────

    public function test_simulated_pay_is_off_unless_online_payments_enabled(): void
    {
        config(['app.online_payments' => false]);
        Sanctum::actingAs($this->tenant);

        $this->postJson("/api/me/invoices/{$this->invoice->id}/pay", ['method' => 'fpx'])
            ->assertForbidden()->assertJson(['code' => 'online_payments_unavailable']);
        $this->assertSame('overdue', $this->invoice->fresh()->status->value);

        config(['app.online_payments' => true]);
        $this->postJson("/api/me/invoices/{$this->invoice->id}/pay", ['method' => 'fpx'])->assertCreated();
    }
}
