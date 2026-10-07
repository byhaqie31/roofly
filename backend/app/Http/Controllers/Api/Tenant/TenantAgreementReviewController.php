<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Enums\AgreementStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AgreementResource;
use App\Models\Agreement;
use App\Notifications\AgreementReviewed;
use Illuminate\Http\Request;

/** Tenant's answer to an agreement the owner sent (spec 2026-10-07 agreement-review § 3). */
class TenantAgreementReviewController extends Controller
{
    /** pending_review → accepted. The owner still activates it. */
    public function accept(Request $request, Agreement $agreement)
    {
        $this->authorizeReview($request, $agreement);

        $agreement->update(['status' => AgreementStatus::ACCEPTED, 'accepted_at' => now()]);
        $this->notifyOwner($agreement, accepted: true);

        return new AgreementResource($agreement->fresh());
    }

    /** pending_review → draft with the tenant's note for the owner. */
    public function requestChanges(Request $request, Agreement $agreement)
    {
        $this->authorizeReview($request, $agreement);
        $data = $request->validate(['note' => 'required|string|max:500']);

        $agreement->update([
            'status'               => AgreementStatus::DRAFT,
            'sent_at'              => null,
            'review_note'          => $data['note'],
            'changes_requested_at' => now(),
        ]);
        $this->notifyOwner($agreement, accepted: false, note: $data['note']);

        return new AgreementResource($agreement->fresh());
    }

    private function authorizeReview(Request $request, Agreement $agreement): void
    {
        abort_if($agreement->tenant_id !== $request->user()->id, 403);
        abort_unless($agreement->status === AgreementStatus::PENDING_REVIEW, 409, 'This agreement is not awaiting your review.');
    }

    private function notifyOwner(Agreement $agreement, bool $accepted, ?string $note = null): void
    {
        $owner = $agreement->unit?->property?->owner;
        $owner?->notify(new AgreementReviewed($agreement, $accepted, $note));
    }
}
