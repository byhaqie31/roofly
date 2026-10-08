<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Notifications\PaymentClaimRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Owner answers a tenant's transfer claim (spec 2026-10-08 payout-accounts-duitnow § 3.3, § 4). */
class PaymentClaimController extends Controller
{
    /** pending → successful, invoice → paid. `paidAt` optionally corrects the date the money landed. */
    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeOwner($request, $payment);
        $data = $request->validate(['paidAt' => 'sometimes|nullable|date']);
        $this->assertPending($payment);

        DB::transaction(function () use ($payment, $data) {
            $payment->update(array_filter([
                'status'       => PaymentStatus::SUCCESSFUL,
                'confirmed_at' => now(),
                'paid_at'      => isset($data['paidAt']) ? Carbon::parse($data['paidAt'])->startOfDay() : null,
            ], fn ($v) => $v !== null));
            $payment->invoice->update(['status' => InvoiceStatus::PAID]);
        });

        return $this->envelope($payment);
    }

    /** pending → failed with a reason; the invoice is untouched so the tenant can claim again. */
    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeOwner($request, $payment);
        $data = $request->validate(['reason' => 'required|string|max:300']);
        $this->assertPending($payment);

        $payment->update([
            'status'           => PaymentStatus::FAILED,
            'rejection_reason' => trim($data['reason']),
        ]);

        $invoice = $payment->invoice;
        $invoice->agreement?->tenant?->notify(new PaymentClaimRejected($payment, $invoice));

        return $this->envelope($payment);
    }

    private function envelope(Payment $payment): JsonResponse
    {
        return response()->json([
            'payment' => (new PaymentResource($payment->fresh()))->resolve(),
            'invoice' => (new InvoiceResource($payment->invoice->fresh()))->resolve(),
        ]);
    }

    private function assertPending(Payment $payment): void
    {
        abort_unless($payment->status === PaymentStatus::PENDING, 409, 'This payment has already been answered.');
    }

    private function authorizeOwner(Request $request, Payment $payment): void
    {
        abort_if(
            $payment->invoice?->agreement?->unit?->property?->owner_id !== $request->user()->id,
            403
        );
    }
}
