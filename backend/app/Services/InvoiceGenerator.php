<?php

namespace App\Services;

use App\Enums\AgreementStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Agreement;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rent invoices derived from agreements (ADR-006, spec 2026-10-07 § 3).
 *
 * Policy, chosen by the owner: invoices start from the NEXT due date on or
 * after activation — never back-filled. A landlord who moves an existing
 * tenancy into Roofly must not see months of instant "overdue" for rent that
 * was collected before the app existed, and no payment record is ever
 * fabricated.
 *
 * Idempotent by construction: `invoices(agreement_id, due_date)` is unique
 * and existing periods (any status, cancelled included) are skipped, so the
 * controller hooks and the daily `invoices:roll` command can overlap safely.
 */
class InvoiceGenerator
{
    /** How far ahead a pending invoice exists before its due date. Mirrors the demo layer. */
    public const HORIZON_DAYS = 30;

    private const NUMBER_PREFIX = 'INV-';

    /**
     * Create every missing invoice whose due date falls within
     * [max(start_date, today), min(end_date, today + HORIZON_DAYS)].
     *
     * @return Collection<int, Invoice> the invoices created by this call
     */
    public function generateFor(Agreement $agreement, ?CarbonInterface $today = null): Collection
    {
        $created = collect();
        if ($agreement->status !== AgreementStatus::ACTIVE) {
            return $created;
        }

        $today   = CarbonImmutable::instance($today ?? now())->startOfDay();
        $start   = CarbonImmutable::instance($agreement->start_date)->startOfDay();
        $end     = CarbonImmutable::instance($agreement->end_date)->startOfDay();
        $floor   = $start->greaterThan($today) ? $start : $today;
        $horizon = $today->addDays(self::HORIZON_DAYS)->min($end);

        // First candidate: this month's due day, or next month's if it already passed the floor.
        // rent_due_day is capped at 28 by validation, so setDay never overflows.
        $due = $floor->setDay($agreement->rent_due_day);
        if ($due->lessThan($floor)) {
            $due = $due->addMonthNoOverflow();
        }

        $existing = $agreement->invoices()->withTrashed()->get(['due_date'])
            ->map(fn (Invoice $i) => $i->due_date->toDateString())
            ->all();

        while ($due->lessThanOrEqualTo($horizon)) {
            if (! in_array($due->toDateString(), $existing, true)) {
                $invoice = $this->create($agreement, $due);
                if ($invoice !== null) {
                    $created->push($invoice);
                }
            }
            $due = $due->addMonthNoOverflow();
        }

        return $created;
    }

    /** Agreement ended early: stop charging for periods that haven't come due yet. */
    public function cancelFuture(Agreement $agreement, ?CarbonInterface $today = null): int
    {
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();

        return $agreement->invoices()
            ->where('status', InvoiceStatus::PENDING->value)
            ->whereDate('due_date', '>', $today->toDateString())
            ->update(['status' => InvoiceStatus::CANCELLED->value]);
    }

    /**
     * pending → overdue the day after due_date, snapshotting the agreement's
     * late fee once. Already-overdue invoices are untouched, so the fee never stacks.
     *
     * Skips an invoice with a pending transfer claim dated on or before its due
     * date — a tenant who paid on time isn't fined while the owner hasn't looked
     * (spec 2026-10-08 § 5). A rejected claim, or one dated after the due date,
     * doesn't protect it.
     */
    public function markOverdue(?CarbonInterface $today = null): int
    {
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();
        $count = 0;

        Invoice::where('status', InvoiceStatus::PENDING->value)
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('payments')
                ->whereColumn('payments.invoice_id', 'invoices.id')
                ->where('payments.status', PaymentStatus::PENDING->value)
                // date() on both sides: MySQL DATE vs sqlite's 'Y-m-d H:i:s' text.
                ->whereRaw('date(payments.paid_at) <= date(invoices.due_date)'))
            ->with('agreement')
            ->chunkById(200, function (Collection $invoices) use (&$count) {
                foreach ($invoices as $invoice) {
                    $invoice->update([
                        'status'         => InvoiceStatus::OVERDUE,
                        'late_fee_cents' => (int) ($invoice->agreement?->late_fee_cents ?? 0),
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    /** Next `INV-NNNN` in the portfolio-wide sequence (same scheme as DemoSeeder). */
    public function nextNumber(): string
    {
        // Longest numbers sort first, then lexically — so the highest numeric
        // suffix is in the first few rows even once we pass INV-9999.
        $max = Invoice::withTrashed()
            ->where('invoice_number', 'like', self::NUMBER_PREFIX . '%')
            ->orderByRaw('LENGTH(invoice_number) DESC')
            ->orderBy('invoice_number', 'desc')
            ->limit(50)
            ->pluck('invoice_number')
            ->map(fn (string $n) => substr($n, strlen(self::NUMBER_PREFIX)))
            ->filter(fn (string $suffix) => ctype_digit($suffix))
            ->map(fn (string $suffix) => (int) $suffix)
            ->max() ?? 0;

        return self::NUMBER_PREFIX . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Insert one period. A concurrent writer can win the invoice number or the
     * period itself; both surface as a unique violation, so retry the number a
     * couple of times and treat an already-present period as "not ours".
     */
    private function create(Agreement $agreement, CarbonInterface $dueDate): ?Invoice
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(fn () => Invoice::create([
                    'agreement_id'   => $agreement->id,
                    'invoice_number' => $this->nextNumber(),
                    'amount_cents'   => $agreement->rent_amount_cents,
                    'late_fee_cents' => 0,
                    'due_date'       => $dueDate->toDateString(),
                    'status'         => InvoiceStatus::PENDING,
                ]));
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }
                $periodTaken = $agreement->invoices()->withTrashed()
                    ->whereDate('due_date', $dueDate->toDateString())
                    ->exists();
                if ($periodTaken) {
                    return null;
                }
                if ($attempt === 3) {
                    throw $e;
                }
            }
        }

        return null;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // 23000 = MySQL / SQLite integrity constraint, 23505 = Postgres unique_violation
        return in_array((string) $e->getCode(), ['23000', '23505'], true);
    }
}
