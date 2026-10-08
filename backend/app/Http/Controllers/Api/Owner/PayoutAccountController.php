<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayoutAccountRequest;
use App\Http\Requests\UpdatePayoutAccountRequest;
use App\Http\Resources\PayoutAccountResource;
use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Owner payout accounts (spec 2026-10-08 payout-accounts-duitnow § 3.1, § 4).
 * Invariant: an owner with ≥1 account has exactly one default.
 */
class PayoutAccountController extends Controller
{
    public function index(Request $request)
    {
        return PayoutAccountResource::collection($this->listFor($request->user()));
    }

    public function store(StorePayoutAccountRequest $request)
    {
        $owner = $request->user();

        $account = DB::transaction(function () use ($request, $owner) {
            $isFirst = ! $owner->payoutAccounts()->exists();
            $makeDefault = $isFirst || $request->boolean('isDefault');

            if ($makeDefault) {
                $owner->payoutAccounts()->update(['is_default' => false]);
            }

            return $owner->payoutAccounts()->create(array_merge(
                $request->toModelAttributes(),
                ['is_default' => $makeDefault],
            ));
        });

        return (new PayoutAccountResource($account->loadCount('agreements')))
            ->response()->setStatusCode(201);
    }

    public function update(UpdatePayoutAccountRequest $request, PayoutAccount $payoutAccount)
    {
        $this->authorizeOwner($request, $payoutAccount);

        DB::transaction(function () use ($request, $payoutAccount) {
            $payoutAccount->update($request->toModelAttributes());

            // Only ever promote here; un-defaulting is done by defaulting another account.
            if ($request->boolean('isDefault') && ! $payoutAccount->is_default) {
                $this->makeDefault($payoutAccount);
            }
        });

        return new PayoutAccountResource($payoutAccount->fresh()->loadCount('agreements'));
    }

    public function setDefault(Request $request, PayoutAccount $payoutAccount)
    {
        $this->authorizeOwner($request, $payoutAccount);

        DB::transaction(fn () => $this->makeDefault($payoutAccount));

        return PayoutAccountResource::collection($this->listFor($request->user()));
    }

    public function destroy(Request $request, PayoutAccount $payoutAccount)
    {
        $this->authorizeOwner($request, $payoutAccount);

        DB::transaction(function () use ($payoutAccount) {
            $wasDefault = $payoutAccount->is_default;
            $ownerId = $payoutAccount->owner_id;

            // agreements/payments.payout_account_id are nullOnDelete: those agreements fall back to the default.
            $payoutAccount->delete();

            if ($wasDefault) {
                PayoutAccount::where('owner_id', $ownerId)
                    ->orderBy('created_at')->orderBy('id')
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });

        return response()->json(null, 204);
    }

    private function makeDefault(PayoutAccount $account): void
    {
        PayoutAccount::where('owner_id', $account->owner_id)
            ->whereKeyNot($account->id)
            ->update(['is_default' => false]);
        $account->update(['is_default' => true]);
    }

    /** Default first, then oldest. */
    private function listFor(User $owner)
    {
        return $owner->payoutAccounts()
            ->withCount('agreements')
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    private function authorizeOwner(Request $request, PayoutAccount $account): void
    {
        abort_if($account->owner_id !== $request->user()->id, 403);
    }
}
