<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $property->id, 'user_id' => $this->owner->id]);
        $unit = Unit::factory()->create(['property_id' => $property->id]);
        $agreement = Agreement::factory()->create(['unit_id' => $unit->id]);
        $this->invoice = Invoice::factory()->create(['agreement_id' => $agreement->id]);
        Sanctum::actingAs($this->owner);
    }

    public function test_expand_returns_invoice_envelopes(): void
    {
        Payment::factory()->create(['invoice_id' => $this->invoice->id]);
        $res = $this->getJson('/api/invoices?expand=agreement,unit,property,tenant,payments')->assertOk();
        $row = $res->json()[0];
        $this->assertSame(['invoice', 'agreement', 'unit', 'property', 'tenant', 'payments', 'payoutAccount'], array_keys($row));
        $this->assertSame(180000, $row['invoice']['amount']);
        $this->assertSame(180000, $row['payments'][0]['amount']);
        $this->assertSame(['id', 'invoiceId', 'amount', 'method', 'status', 'paidAt', 'reference', 'createdAt', 'payoutAccountId', 'note', 'rejectionReason', 'confirmedAt'], array_keys($row['payments'][0]));
    }

    public function test_record_payment_accepts_camel_case_and_marks_paid(): void
    {
        $res = $this->postJson("/api/invoices/{$this->invoice->id}/payments", [
            'invoiceId' => $this->invoice->id, // sent by frontend, path param wins
            'amount'    => 185000,
            'method'    => 'transfer',
            'paidAt'    => '2026-07-15T10:00:00.000Z',
            'reference' => 'MBB-123',
        ])->assertCreated();

        $this->assertSame(['payment', 'invoice'], array_keys($res->json()));
        $this->assertSame(185000, $res->json('payment.amount'));
        $this->assertSame('paid', $res->json('invoice.status'));
    }

    public function test_update_status(): void
    {
        $this->patchJson("/api/invoices/{$this->invoice->id}/status", ['status' => 'overdue'])
            ->assertOk()->assertJsonPath('status', 'overdue');
    }

    public function test_send_returns_sent_at(): void
    {
        $res = $this->postJson("/api/invoices/{$this->invoice->id}/send")->assertOk();
        $this->assertSame(['sentAt'], array_keys($res->json()));
    }

    public function test_update_status_via_bare_invoice_patch(): void
    {
        $this->patchJson("/api/invoices/{$this->invoice->id}", ['status' => 'cancelled'])
            ->assertOk()->assertJsonPath('status', 'cancelled');
    }
}
