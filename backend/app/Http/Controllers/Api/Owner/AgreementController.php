<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\AgreementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgreementRequest;
use App\Http\Requests\UpdateAgreementRequest;
use App\Http\Resources\AgreementResource;
use App\Http\Resources\AgreementWithRefsResource;
use App\Models\Agreement;
use App\Models\Unit;
use App\Services\InvoiceGenerator;
use Illuminate\Http\Request;

class AgreementController extends Controller
{
    public function index(Request $request)
    {
        $query = Agreement::whereHas('unit.property', fn ($q) =>
            $q->where('owner_id', $request->user()->id)
        )->latest();

        if ($request->filled('expand')) {
            return AgreementWithRefsResource::collection(
                $query->with(['unit.property.coOwners', 'tenant'])->get()
            );
        }

        return AgreementResource::collection($query->get());
    }

    public function store(StoreAgreementRequest $request, InvoiceGenerator $invoices)
    {
        $unit = Unit::findOrFail($request->validated('unitId'));
        abort_if($unit->property->owner_id !== $request->user()->id, 403);

        $agreement = Agreement::create($request->toModelAttributes());

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

        $before = $agreement->status;
        $agreement->update($attributes);

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

    private function authorizeOwner(Request $request, Agreement $agreement): void
    {
        abort_if(
            $agreement->unit->property->owner_id !== $request->user()->id,
            403
        );
    }
}
