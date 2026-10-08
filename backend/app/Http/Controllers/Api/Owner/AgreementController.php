<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\AgreementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgreementRequest;
use App\Http\Requests\UpdateAgreementRequest;
use App\Http\Resources\AgreementResource;
use App\Http\Resources\AgreementWithRefsResource;
use App\Models\Agreement;
use App\Models\PayoutAccount;
use App\Models\Unit;
use App\Notifications\AgreementSent;
use App\Services\InvoiceGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AgreementController extends Controller
{
    public function index(Request $request)
    {
        $query = Agreement::whereHas('unit.property', fn ($q) =>
            $q->where('owner_id', $request->user()->id)
        )->latest();

        if ($request->filled('expand')) {
            return AgreementWithRefsResource::collection(
                $query->with(['unit.property.coOwners', 'tenant', ...Agreement::PAYOUT_RELATIONS])->get()
            );
        }

        return AgreementResource::collection($query->get());
    }

    public function store(StoreAgreementRequest $request, InvoiceGenerator $invoices)
    {
        $unit = Unit::findOrFail($request->validated('unitId'));
        abort_if($unit->property->owner_id !== $request->user()->id, 403);

        $attributes = $request->toModelAttributes();
        $this->assertOwnPayoutAccount($request, $attributes);

        $agreement = Agreement::create($attributes);

        // ADR-006: an active agreement gets its first invoice (next due date onward).
        if ($agreement->status === AgreementStatus::ACTIVE) {
            $invoices->generateFor($agreement);
        }

        return (new AgreementResource($agreement))->response()->setStatusCode(201);
    }

    public function show(Request $request, Agreement $agreement)
    {
        $this->authorizeOwner($request, $agreement);

        return new AgreementResource($agreement);
    }

    public function update(UpdateAgreementRequest $request, Agreement $agreement, InvoiceGenerator $invoices)
    {
        $this->authorizeOwner($request, $agreement);

        $attributes = $request->toModelAttributes();
        if (isset($attributes['unit_id'])) {
            $unit = Unit::findOrFail($attributes['unit_id']);
            abort_if($unit->property->owner_id !== $request->user()->id, 403);
        }
        $this->assertOwnPayoutAccount($request, $attributes);

        $before = $agreement->status;
        $agreement->update($attributes);

        // The tenant agreed to specific terms: editing any of them while sent or
        // accepted voids that and sends the agreement back to draft (re-send).
        if ($before->isUnderReview() && $agreement->wasChanged(Agreement::TERM_COLUMNS)) {
            $agreement->forceFill(['status' => AgreementStatus::DRAFT, 'sent_at' => null, 'accepted_at' => null])->save();
        }

        // Idempotent: activating (or extending) picks up missing periods; ending
        // early cancels pending periods that haven't come due. Existing invoices
        // are never rewritten when rent or the due day changes.
        if ($agreement->status === AgreementStatus::ACTIVE) {
            $invoices->generateFor($agreement);
        } elseif ($agreement->status === AgreementStatus::TERMINATED && $before !== AgreementStatus::TERMINATED) {
            $invoices->cancelFuture($agreement);
        }

        return new AgreementResource($agreement);
    }

    public function destroy(Request $request, Agreement $agreement)
    {
        $this->authorizeOwner($request, $agreement);

        $agreement->delete();

        return response()->json(null, 204);
    }

    /** draft → pending_review: email the tenant a link to review the terms. */
    public function send(Request $request, Agreement $agreement)
    {
        $this->authorizeOwner($request, $agreement);
        abort_unless($agreement->status === AgreementStatus::DRAFT, 409, 'Only drafts can be sent for review.');

        $agreement->update([
            'status'      => AgreementStatus::PENDING_REVIEW,
            'sent_at'     => now(),
            'review_note' => null,
        ]);
        $agreement->tenant?->notify(new AgreementSent($agreement));

        return new AgreementResource($agreement->fresh());
    }

    /** pending_review → draft without the tenant answering. */
    public function withdraw(Request $request, Agreement $agreement)
    {
        $this->authorizeOwner($request, $agreement);
        abort_unless($agreement->status === AgreementStatus::PENDING_REVIEW, 409, 'Only an agreement awaiting review can be withdrawn.');

        $agreement->update(['status' => AgreementStatus::DRAFT, 'sent_at' => null]);

        return new AgreementResource($agreement->fresh());
    }

    /** payoutAccountId must be one of the caller's own accounts (or null = default). */
    private function assertOwnPayoutAccount(Request $request, array $attributes): void
    {
        $id = $attributes['payout_account_id'] ?? null;
        if ($id === null) {
            return;
        }
        $mine = PayoutAccount::whereKey($id)->where('owner_id', $request->user()->id)->exists();
        if (! $mine) {
            throw ValidationException::withMessages([
                'payoutAccountId' => 'Choose one of your payout accounts.',
            ]);
        }
    }

    private function authorizeOwner(Request $request, Agreement $agreement): void
    {
        abort_if(
            $agreement->unit->property->owner_id !== $request->user()->id,
            403
        );
    }
}
