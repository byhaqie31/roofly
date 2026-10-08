<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unitId'        => 'sometimes|uuid|exists:units,id',
            'tenantId'      => 'sometimes|uuid|exists:users,id',
            'startDate'     => 'sometimes|date',
            'endDate'       => 'sometimes|date',
            'rentAmount'    => 'sometimes|integer|min:1',
            'depositAmount' => 'sometimes|integer|min:0',
            'lateFee'       => 'nullable|integer|min:0',
            'rentDueDay'    => 'sometimes|integer|min:1|max:28',
            'status'        => 'sometimes|in:draft,active,expired,terminated',
            // Not a term column — null = the owner's default (spec 2026-10-08 § 3.2). Ownership checked in the controller.
            'payoutAccountId' => 'sometimes|nullable|uuid',
        ];
    }

    /** Column-keyed payload for Agreement::update(). */
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
