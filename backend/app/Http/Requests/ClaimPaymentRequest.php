<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Tenant: "I've paid" by DuitNow / bank transfer (spec 2026-10-08 § 4). */
class ClaimPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // "Today" in Malaysia — the app runs in UTC, and a tenant paying at 7am MYT is still on yesterday's UTC date.
        $today = now('Asia/Kuala_Lumpur')->toDateString();

        return [
            'reference' => 'required|string|max:100',
            'paidAt'    => "required|date|before_or_equal:{$today}",
            'note'      => 'nullable|string|max:500',
        ];
    }
}
