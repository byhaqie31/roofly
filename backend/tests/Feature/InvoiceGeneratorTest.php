<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Services\InvoiceGenerator;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec: docs/superpowers/specs/2026-10-07-invoice-generation-tenant-invite-design.md § 3.
 * Policy chosen by the owner: invoices start from the NEXT due date — never back-fill.
 */
class InvoiceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function agreement(array $attrs = []): Agreement
    {
        return Agreement::factory()->create(array_merge([
            'start_date'   => '2026-01-01',
            'end_date'     => '2027-12-31',
            'rent_due_day' => 1,
            'status'       => 'active',
        ], $attrs));
    }

    /** @return string[] */
    private function dueDates(Agreement $a): array
    {
        return $a->invoices()->orderBy('due_date')->get()->map(fn (Invoice $i) => $i->due_date->toDateString())->all();
    }

    public function test_past_start_generates_from_the_next_due_date_only(): void
    {
        $a = $this->agreement(); // running since January, rent due on the 1st

        $created = app(InvoiceGenerator::class)->generateFor($a, Carbon::parse('2026-10-07'));

        $this->assertSame(['2026-11-01'], $this->dueDates($a)); // October was collected outside Roofly
        $this->assertCount(1, $created);
        $this->assertSame('pending', $created->first()->status->value);
        $this->assertSame(180000, $created->first()->amount_cents);
        $this->assertSame(0, $created->first()->late_fee_cents);
    }

    public function test_due_day_falling_today_is_included(): void
    {
        $a = $this->agreement();
        app(InvoiceGenerator::class)->generateFor($a, Carbon::parse('2026-10-01'));
        $this->assertSame(['2026-10-01'], $this->dueDates($a));
    }

    public function test_generation_horizon_is_thirty_days(): void
    {
        $a = $this->agreement(['rent_due_day' => 5]);
        app(InvoiceGenerator::class)->generateFor($a, Carbon::parse('2026-10-07')); // horizon 2026-11-06
        $this->assertSame(['2026-11-05'], $this->dueDates($a));

        $b = $this->agreement(['rent_due_day' => 1]);
        app(InvoiceGenerator::class)->generateFor($b, Carbon::parse('2026-10-02')); // horizon 2026-11-01 exactly
        $this->assertSame(['2026-11-01'], $this->dueDates($b));
    }

    public function test_future_start_outside_the_horizon_generates_nothing(): void
    {
        $a = $this->agreement(['start_date' => '2027-01-01']);
        app(InvoiceGenerator::class)->generateFor($a, Carbon::parse('2026-10-07'));
        $this->assertSame([], $this->dueDates($a));
    }

    public function test_future_start_inside_the_horizon_begins_at_the_first_due_day_on_or_after_start(): void
    {
        $a = $this->agreement(['start_date' => '2026-10-15', 'rent_due_day' => 20]);
        app(InvoiceGenerator::class)->generateFor($a, Carbon::parse('2026-10-07'));
        $this->assertSame(['2026-10-20'], $this->dueDates($a));

        $b = $this->agreement(['start_date' => '2026-10-15', 'rent_due_day' => 10]); // Oct 10 is before start → Nov 10, outside horizon
        app(InvoiceGenerator::class)->generateFor($b, Carbon::parse('2026-10-07'));
        $this->assertSame([], $this->dueDates($b));
    }

    public function test_end_date_caps_generation(): void
    {
        $a = $this->agreement(['end_date' => '2026-10-20']);
        app(InvoiceGenerator::class)->generateFor($a, Carbon::parse('2026-10-07'));
        $this->assertSame([], $this->dueDates($a));
    }

    public function test_non_active_agreements_generate_nothing(): void
    {
        foreach (['draft', 'expired', 'terminated'] as $status) {
            $a = $this->agreement(['status' => $status]);
            app(InvoiceGenerator::class)->generateFor($a, Carbon::parse('2026-10-01'));
            $this->assertSame([], $this->dueDates($a), "status {$status}");
        }
    }

    public function test_rerun_is_idempotent_and_rolls_forward_with_time(): void
    {
        $a = $this->agreement();
        $gen = app(InvoiceGenerator::class);

        $gen->generateFor($a, Carbon::parse('2026-10-07'));
        $this->assertCount(0, $gen->generateFor($a, Carbon::parse('2026-10-07')));

        $gen->generateFor($a, Carbon::parse('2026-11-07'));
        $this->assertSame(['2026-11-01', '2026-12-01'], $this->dueDates($a));
    }

    public function test_a_cancelled_period_is_not_regenerated(): void
    {
        $a = $this->agreement();
        $gen = app(InvoiceGenerator::class);
        $gen->generateFor($a, Carbon::parse('2026-10-07'));
        $a->invoices()->update(['status' => 'cancelled']);

        $gen->generateFor($a, Carbon::parse('2026-10-07'));

        $this->assertSame(['2026-11-01'], $this->dueDates($a));
        $this->assertSame('cancelled', $a->invoices()->first()->status->value);
    }

    public function test_invoice_numbers_continue_the_global_sequence(): void
    {
        Invoice::factory()->create(['invoice_number' => 'INV-0042', 'due_date' => '2026-01-01']);
        Invoice::factory()->create(['invoice_number' => 'INV-LEGACY', 'due_date' => '2026-02-01']); // non-numeric suffix ignored

        $created = app(InvoiceGenerator::class)->generateFor($this->agreement(), Carbon::parse('2026-10-07'));

        $this->assertSame('INV-0043', $created->first()->invoice_number);
    }

    public function test_database_rejects_a_duplicate_period_for_the_same_agreement(): void
    {
        $a = $this->agreement();
        Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-11-01']);

        $this->expectException(QueryException::class);
        Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-11-01']);
    }

    public function test_cancel_future_leaves_due_and_paid_invoices_alone(): void
    {
        $a = $this->agreement();
        Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-10-01', 'status' => 'pending']);
        Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-11-01', 'status' => 'pending']);
        Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-12-01', 'status' => 'paid']);

        $cancelled = app(InvoiceGenerator::class)->cancelFuture($a, Carbon::parse('2026-10-07'));

        $this->assertSame(1, $cancelled);
        $this->assertSame(
            ['pending', 'cancelled', 'paid'],
            $a->invoices()->orderBy('due_date')->get()->map(fn (Invoice $i) => $i->status->value)->all()
        );
    }

    public function test_mark_overdue_applies_the_late_fee_once_and_skips_paid(): void
    {
        $a = $this->agreement(['late_fee_cents' => 5000]);
        $due    = Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-10-01', 'status' => 'pending']);
        $notYet = Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-10-02', 'status' => 'pending']);
        $paid   = Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-09-01', 'status' => 'paid']);

        $gen = app(InvoiceGenerator::class);
        $this->assertSame(1, $gen->markOverdue(Carbon::parse('2026-10-02'))); // the day after Oct 1
        $this->assertSame('overdue', $due->fresh()->status->value);
        $this->assertSame(5000, $due->fresh()->late_fee_cents);
        $this->assertSame('pending', $notYet->fresh()->status->value); // due today, not yet late
        $this->assertSame('paid', $paid->fresh()->status->value);

        $this->assertSame(0, $gen->markOverdue(Carbon::parse('2026-10-02'))); // idempotent
        $this->assertSame(5000, $due->fresh()->late_fee_cents);                 // fee not stacked
    }

    // ── Pending transfer claims hold off the overdue flip (spec 2026-10-08 § 5) ──

    private function claimOn(Invoice $invoice, string $paidAt, string $status = 'pending'): void
    {
        \App\Models\Payment::factory()->create([
            'invoice_id' => $invoice->id, 'method' => 'transfer', 'status' => $status, 'paid_at' => $paidAt,
        ]);
    }

    public function test_on_time_pending_claim_skips_the_overdue_flip(): void
    {
        $a = $this->agreement(['late_fee_cents' => 5000]);
        $onTime   = Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-09-01', 'status' => 'pending']);
        $sameDay  = Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-10-01', 'status' => 'pending']);
        $this->claimOn($onTime, '2026-08-30 14:00:00');
        $this->claimOn($sameDay, '2026-10-01 23:30:00'); // later in the day still counts as on time

        $this->assertSame(0, app(InvoiceGenerator::class)->markOverdue(Carbon::parse('2026-10-07')));
        $this->assertSame('pending', $onTime->fresh()->status->value);
        $this->assertSame(0, $onTime->fresh()->late_fee_cents);
        $this->assertSame('pending', $sameDay->fresh()->status->value);
    }

    public function test_rejected_claim_lets_the_next_roll_flip_it_with_the_fee(): void
    {
        $a = $this->agreement(['late_fee_cents' => 5000]);
        $inv = Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-10-01', 'status' => 'pending']);
        $this->claimOn($inv, '2026-09-30');
        $gen = app(InvoiceGenerator::class);

        $this->assertSame(0, $gen->markOverdue(Carbon::parse('2026-10-03')));

        $inv->payments()->update(['status' => 'failed', 'rejection_reason' => 'Not received']);
        $this->assertSame(1, $gen->markOverdue(Carbon::parse('2026-10-04')));
        $this->assertSame('overdue', $inv->fresh()->status->value);
        $this->assertSame(5000, $inv->fresh()->late_fee_cents);
    }

    public function test_claim_dated_after_the_due_date_does_not_block_the_flip(): void
    {
        $a = $this->agreement(['late_fee_cents' => 5000]);
        $inv = Invoice::factory()->create(['agreement_id' => $a->id, 'due_date' => '2026-10-01', 'status' => 'pending']);
        $this->claimOn($inv, '2026-10-02');

        $this->assertSame(1, app(InvoiceGenerator::class)->markOverdue(Carbon::parse('2026-10-03')));
        $this->assertSame('overdue', $inv->fresh()->status->value);
    }
}
