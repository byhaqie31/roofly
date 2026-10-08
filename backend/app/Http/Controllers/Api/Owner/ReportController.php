<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $ownerId = $request->user()->id;
        $now     = now();

        // Income this month — successful payments on invoices owned by this owner
        $incomeThisMonth = $this->paymentsQuery($ownerId)
            ->whereYear('payments.paid_at', $now->year)
            ->whereMonth('payments.paid_at', $now->month)
            ->sum('payments.amount_cents');

        // Outstanding — pending + overdue invoices
        $outstandingInvoices = Invoice::whereHas('agreement.unit.property', fn ($q) =>
            $q->where('owner_id', $ownerId)
        )
            ->whereIn('status', [InvoiceStatus::PENDING, InvoiceStatus::OVERDUE])
            ->get(['id', 'amount_cents', 'late_fee_cents', 'status', 'due_date']);

        // Expiring agreements (within 60 days)
        $expiringAgreements = \App\Models\Agreement::with(['unit.property', 'tenant'])
            ->whereHas('unit.property', fn ($q) => $q->where('owner_id', $ownerId))
            ->where('status', 'active')
            ->whereBetween('end_date', [$now->toDateString(), $now->addDays(60)->toDateString()])
            ->get();

        // 12-month income chart
        $monthlyIncome = $this->monthlyIncome($ownerId, $now->year);

        return response()->json([
            'income_this_month_cents'   => $incomeThisMonth,
            'outstanding_cents'         => $outstandingInvoices->sum(fn ($i) => $i->amount_cents + $i->late_fee_cents),
            'outstanding_invoice_count' => $outstandingInvoices->count(),
            'expiring_agreement_count'  => $expiringAgreements->count(),
            'monthly_income'            => $monthlyIncome,
            'expiring_agreements'       => $expiringAgreements,
            'outstanding_invoices'      => $outstandingInvoices,
        ]);
    }

    public function yearly(Request $request, int $year): JsonResponse
    {
        $ownerId = $request->user()->id;

        $monthlyIncome = $this->monthlyIncome($ownerId, $year);

        $properties = Property::with(['units.agreements.invoices'])
            ->where('owner_id', $ownerId)
            ->get();

        return response()->json([
            'year'           => $year,
            'monthly_income' => $monthlyIncome,
            'total_income_cents' => array_sum(array_column($monthlyIncome, 'income_cents')),
            'properties'     => $properties,
        ]);
    }

    public function exportCsv(Request $request, int $year): Response
    {
        // TODO Phase 4: generate and stream CSV
        abort(501, 'CSV export coming in Phase 4.');
    }

    private function paymentsQuery(string $ownerId)
    {
        return \App\Models\Payment::join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->join('agreements', 'invoices.agreement_id', '=', 'agreements.id')
            ->join('units', 'agreements.unit_id', '=', 'units.id')
            ->join('properties', 'units.property_id', '=', 'properties.id')
            ->where('properties.owner_id', $ownerId)
            ->where('payments.status', PaymentStatus::SUCCESSFUL);
    }

    private function monthlyIncome(string $ownerId, int $year): array
    {
        $rows = $this->paymentsQuery($ownerId)
            ->whereYear('payments.paid_at', $year)
            ->selectRaw('MONTH(payments.paid_at) as month, SUM(payments.amount_cents) as income_cents')
            ->groupBy('month')
            ->pluck('income_cents', 'month');

        return array_map(fn ($m) => [
            'month'        => $m,
            'income_cents' => (int) ($rows[$m] ?? 0),
        ], range(1, 12));
    }
}
