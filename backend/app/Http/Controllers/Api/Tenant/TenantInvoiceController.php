<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimPaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\InvoiceWithRefsResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\PaymentClaimSubmitted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TenantInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::whereHas('agreement', fn ($q) =>
            $q->where('tenant_id', $request->user()->id)
        )->orderBy('due_date', 'desc');

        if ($request->filled('expand')) {
            return InvoiceWithRefsResource::collection(
                $query->with(Invoice::refsRelations())->get()
            );
        }

        return InvoiceResource::collection($query->get());
    }

    /**
     * "I've paid" by DuitNow / bank transfer (spec 2026-10-08 § 4). Records a
     * pending transfer payment against the resolved payout account; the owner
     * confirms (→ paid) or rejects it. The invoice status is untouched here.
     */
    public function claim(ClaimPaymentRequest $request, Invoice $invoice): JsonResponse
    {
        abort_if($invoice->agreement?->tenant_id !== $request->user()->id, 403);
        abort_unless(
            in_array($invoice->status, [InvoiceStatus::PENDING, InvoiceStatus::OVERDUE], true),
            422,
            'Only a pending or overdue invoice can be paid.'
        );

        if ($invoice->pendingPayment()->exists()) {
            return response()->json([
                'message' => 'A payment for this invoice is already awaiting your landlord.',
                'code'    => 'claim_pending',
            ], 409);
        }

        $account = $invoice->agreement->resolvedPayoutAccount();
        if ($account === null) {
            return response()->json([
                'message' => "Your landlord hasn't added payment details yet.",
                'code'    => 'no_payout_account',
            ], 422);
        }

        $data = $request->validated();
        $payment = DB::transaction(fn () => Payment::create([
            'invoice_id'        => $invoice->id,
            'payout_account_id' => $account->id,
            'amount_cents'      => $invoice->totalDueCents(),
            'method'            => PaymentMethod::TRANSFER,
            'status'            => PaymentStatus::PENDING,
            'reference'         => trim($data['reference']),
            'note'              => $data['note'] ?? null,
            'paid_at'           => Carbon::parse($data['paidAt'])->startOfDay(),
        ]));

        $owner = $invoice->agreement->unit?->property?->owner;
        if ($owner && PaymentClaimSubmitted::wantedBy($owner)) {
            $owner->notify(new PaymentClaimSubmitted($payment, $invoice, $request->user()->name));
        }

        return response()->json([
            'payment' => (new PaymentResource($payment->fresh()))->resolve(),
            'invoice' => (new InvoiceResource($invoice->fresh()))->resolve(),
        ], 201);
    }

    /**
     * Simulated FPX pay → paid round-trip, kept as the shell the real gateway
     * slots into. Off unless `config('app.online_payments')` (env ONLINE_PAYMENTS),
     * so nobody can self-mark rent paid on UAT/prod (spec 2026-10-08 § 4).
     */
    public function pay(Request $request, Invoice $invoice): JsonResponse
    {
        if (! config('app.online_payments')) {
            return response()->json([
                'message' => 'Online payments are not available yet.',
                'code'    => 'online_payments_unavailable',
            ], 403);
        }

        abort_if(
            $invoice->agreement->tenant_id !== $request->user()->id,
            403
        );
        abort_if(
            $invoice->status === InvoiceStatus::PAID,
            422,
            'Invoice is already paid.'
        );

        $data = $request->validate([
            'method' => 'required|in:fpx,card,cash,transfer',
        ]);

        // TODO Phase 3: create Billplz bill, redirect to payment URL
        // For now: simulate instant success
        $payment = Payment::create([
            'invoice_id'   => $invoice->id,
            'amount_cents' => $invoice->totalDueCents(),
            'method'       => $data['method'],
            'status'       => PaymentStatus::SUCCESSFUL,
            'paid_at'      => now(),
        ]);

        $invoice->update(['status' => InvoiceStatus::PAID]);

        return response()->json([
            'payment' => (new PaymentResource($payment))->resolve(),
            'invoice' => (new InvoiceResource($invoice->fresh()))->resolve(),
        ], 201);
    }
}
