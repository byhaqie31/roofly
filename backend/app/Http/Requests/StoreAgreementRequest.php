<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unitId'        => 'required|uuid|exists:units,id',
            'tenantId'      => 'required|uuid|exists:users,id',
            'startDate'     => 'required|date',
            'endDate'       => 'required|date|after:startDate',
            'rentAmount'    => 'required|integer|min:1',
            'depositAmount' => 'required|integer|min:0',
            'lateFee'       => 'nullable|integer|min:0',
            'rentDueDay'    => 'required|integer|min:1|max:28',
            'status'        => 'nullable|in:draft,active,expired,terminated',
            // Not a term column — null = the owner's default (spec 2026-10-08 § 3.2). Ownership checked in the controller.
            'payoutAccountId' => 'sometimes|nullable|uuid',
        ];
    }

    /** Column-keyed payload for Agreement::create(). */
    public function toModelAttributes(): array
    {
        $v = $this->validated();
        $map = [
            'unitId'        => 'unit_id',
            'tenantId'      => 'tenant_id',
            'startDate'     => 'start_date',
            'endDate'       => 'end_date',
            'rentAmount'    => 'rent_amount_cents',
            'depositAmount' => 'deposit_amount_cents',
            'lateFee'       => 'late_fee_cents',
            'rentDueDay'    => 'rent_due_day',
            'payoutAccountId' => 'payout_account_id',
        ];
        $out = [];
        foreach ($v as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }
        return $out;
    }
}
