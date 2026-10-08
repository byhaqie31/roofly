<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordPaymentRequest;
use App\Http\Requests\UpdateInvoiceStatusRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\InvoiceWithRefsResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::whereHas('agreement.unit.property', fn ($q) =>
            $q->where('owner_id', $request->user()->id)
        );

        if ($request->input('status') === 'awaiting') {
            // Derived, not stored: has a tenant transfer claim pending (spec 2026-10-08 § 3.3).
            $query->whereHas('pendingPayment');
        } elseif ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('year')) {
            $query->whereYear('due_date', $request->year);
        }
        if ($request->filled('month')) {
            $query->whereMonth('due_date', $request->month);
        }

        $query->orderBy('due_date', 'desc');

        if ($request->filled('expand')) {
            return InvoiceWithRefsResource::collection(
                $query->with(Invoice::refsRelations())->get()
            );
        }

        return InvoiceResource::collection($query->get());
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->authorizeOwner($request, $invoice);

        if ($request->filled('expand')) {
            $invoice->load(Invoice::refsRelations());

            return new InvoiceWithRefsResource($invoice);
        }

        return new InvoiceResource($invoice);
    }

    public function updateStatus(UpdateInvoiceStatusRequest $request, Invoice $invoice)
    {
        $this->authorizeOwner($request, $invoice);

        $invoice->update($request->validated());

        return new InvoiceResource($invoice);
    }

    public function send(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeOwner($request, $invoice);

        // TODO Phase 4: dispatch InvoiceSentNotification via email + WhatsApp
        return response()->json(['sentAt' => now()->toISOString()]);
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeOwner($request, $invoice);

        $payment = Payment::create(array_merge($request->toModelAttributes(), [
            'invoice_id' => $invoice->id,
            'status'     => PaymentStatus::SUCCESSFUL,
        ]));

        $invoice->update(['status' => InvoiceStatus::PAID]);

        return response()->json([
            'payment' => (new PaymentResource($payment))->resolve(),
            'invoice' => (new InvoiceResource($invoice->fresh()))->resolve(),
        ], 201);
    }

    private function authorizeOwner(Request $request, Invoice $invoice): void
    {
        abort_if(
            $invoice->agreement->unit->property->owner_id !== $request->user()->id,
            403
        );
    }
}
