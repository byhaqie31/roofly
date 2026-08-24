<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\AgreementStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UnitStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single aggregated payload for the owner dashboard. Replaces the six
 * separate list fetches the frontend used to make + compute client-side;
 * everything the dashboard renders is computed here, scoped to the owner.
 *
 * Response shape mirrors the frontend `DashboardData` contract exactly
 * (camelCase, money in integer sen). Kept in lock-step with the mock-mode
 * computation in `composables/useDashboard.ts`.
 */
class DashboardController extends Controller
{
    private const EXPIRY_WINDOW_DAYS = 60;

    private const DAY_SECONDS = 86400;

    public function index(Request $request): JsonResponse
    {
        $ownerId = $request->user()->id;
        $now = now();

        // isEmpty looks at every property (an own-stay home is still a property);
        // everything else is rental-only (spec 2026-08-23 § 5.3).
        $isEmpty = ! Property::where('owner_id', $ownerId)->exists();
        $propertyIds = Property::where('owner_id', $ownerId)->rental()->pluck('id');

        // ── Units / occupancy ──────────────────────────────────────────────
        $units = Unit::whereIn('property_id', $propertyIds)->get(['id', 'status']);
        $unitCount = $units->count();
        $occupiedCount = $units->where('status', UnitStatus::OCCUPIED)->count();
        $occupancyPct = $unitCount > 0
            ? (int) round($occupiedCount / $unitCount * 100)
            : 0;

        // ── Invoices / outstanding ─────────────────────────────────────────
        $invoices = Invoice::whereHas(
            'agreement.unit',
            fn ($q) => $q->whereIn('property_id', $propertyIds)
        )->with('agreement.tenant:id,name')->get();

        $outstandingInvoices = $invoices->whereIn(
            'status',
            [InvoiceStatus::PENDING, InvoiceStatus::OVERDUE]
        );
        $outstanding = (int) $outstandingInvoices
            ->sum(fn ($i) => $i->amount_cents + $i->late_fee_cents);
        $outstandingCount = $outstandingInvoices->count();

        // ── Payments / income (successful only) ────────────────────────────
        $payments = Payment::whereIn('invoice_id', $invoices->pluck('id'))
            ->where('status', PaymentStatus::SUCCESSFUL)
            ->get(['amount_cents', 'paid_at']);

        $thisMonthKey = $now->format('Y-m');
        $monthlyIncome = (int) $payments
            ->filter(fn ($p) => optional($p->paid_at)->format('Y-m') === $thisMonthKey)
            ->sum('amount_cents');

        // Trailing 12 months ending in current month, oldest first.
        $series = [];
        for ($i = 11; $i >= 0; $i--) {
            $series[$now->copy()->subMonthsNoOverflow($i)->format('Y-m')] = 0;
        }
        foreach ($payments as $p) {
            $key = optional($p->paid_at)->format('Y-m');
            if ($key !== null && array_key_exists($key, $series)) {
                $series[$key] += $p->amount_cents;
            }
        }
        $incomeSeries = collect($series)
            ->map(fn ($amount, $key) => ['key' => $key, 'amount' => (int) $amount])
            ->values();

        // ── Agreements / expiring ──────────────────────────────────────────
        $agreements = Agreement::whereHas(
            'unit',
            fn ($q) => $q->whereIn('property_id', $propertyIds)
        )->with('tenant:id,name')->get();

        $nowTs = $now->timestamp;
        $expiring = $agreements->filter(function ($a) use ($nowTs) {
            if ($a->status !== AgreementStatus::ACTIVE || $a->end_date === null) {
                return false;
            }
            $endTs = $a->end_date->timestamp;

            return $endTs >= $nowTs
                && ($endTs - $nowTs) <= self::EXPIRY_WINDOW_DAYS * self::DAY_SECONDS;
        });
        $expiringCount = $expiring->count();

        // ── Tenants who gave notice ────────────────────────────────────────
        $noticeTenants = User::where('role', UserRole::TENANT)
            ->where('status', 'notice_given')
            ->where(fn ($q) => $q
                ->where('invited_by', $ownerId)
                ->orWhereHas('agreements.unit.property', fn ($qq) =>
                    $qq->where('owner_id', $ownerId)
                )
            )->get(['id', 'name']);

        // ── Tickets needing attention ──────────────────────────────────────
        $tickets = Ticket::whereHas(
            'unit',
            fn ($q) => $q->whereIn('property_id', $propertyIds)
        )->get(['id', 'title', 'priority', 'status']);

        $newUrgent = $tickets->filter(fn ($t) =>
            $t->status === TicketStatus::NEW
            && in_array($t->priority, [TicketPriority::HIGH, TicketPriority::URGENT], true)
        );
        $reopened = $tickets->filter(fn ($t) => $t->status === TicketStatus::REOPENED);

        // ── Needs-attention feed (order matches the frontend) ──────────────
        $needsAttention = [];

        foreach ($invoices->where('status', InvoiceStatus::OVERDUE) as $inv) {
            $needsAttention[] = [
                'kind'  => 'overdue',
                'title' => $inv->invoice_number,
                'meta'  => $inv->agreement?->tenant?->name ?? '—',
                'link'  => '/owner/payments',
            ];
        }
        foreach ($expiring as $a) {
            $daysLeft = (int) ceil(($a->end_date->timestamp - $nowTs) / self::DAY_SECONDS);
            $needsAttention[] = [
                'kind'  => 'expiring',
                'title' => $a->tenant?->name ?? 'Agreement',
                'meta'  => $daysLeft . 'd',
                'link'  => '/owner/agreements',
            ];
        }
        foreach ($noticeTenants as $t) {
            $needsAttention[] = [
                'kind'  => 'notice_given',
                'title' => $t->name,
                'meta'  => '',
                'link'  => "/owner/tenants/{$t->id}",
            ];
        }
        foreach ($newUrgent as $t) {
            $needsAttention[] = [
                'kind'  => 'ticket_new',
                'title' => $t->title,
                'meta'  => $t->priority->value,
                'link'  => "/owner/maintenance/{$t->id}",
            ];
        }
        foreach ($reopened as $t) {
            $needsAttention[] = [
                'kind'  => 'ticket_reopened',
                'title' => $t->title,
                'meta'  => $t->priority->value,
                'link'  => "/owner/maintenance/{$t->id}",
            ];
        }

        return response()->json([
            'isEmpty' => $isEmpty,
            'stats'   => [
                'monthlyIncome'    => $monthlyIncome,
                'occupancyPct'     => $occupancyPct,
                'occupiedCount'    => $occupiedCount,
                'unitCount'        => $unitCount,
                'outstanding'      => $outstanding,
                'outstandingCount' => $outstandingCount,
                'expiringCount'    => $expiringCount,
            ],
            'incomeSeries'   => $incomeSeries,
            'needsAttention' => $needsAttention,
        ]);
    }
}
