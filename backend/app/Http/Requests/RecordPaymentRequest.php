<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'    => 'required|integer|min:1',
            'method'    => 'required|in:fpx,card,cash,transfer',
            'paidAt'    => 'required|date',
            'reference' => 'nullable|string|max:255',
        ];
    }

    /** Column-keyed payload for Payment::create(). Route param wins over any invoiceId in the body. */
    public function toModelAttributes(): array
    {
        $v = $this->validated();
        $map = [
            'amount' => 'amount_cents',
            'paidAt' => 'paid_at',
        ];
        $out = [];
        foreach ($v as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }
        return $out;
    }
}
