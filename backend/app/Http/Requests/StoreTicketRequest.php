<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unitId'      => 'required|uuid|exists:units,id',
            'category'    => 'required|in:plumbing,electrical,appliance,structural,pest,other',
            'priority'    => 'required|in:low,medium,high,urgent',
            'title'       => 'required|string|max:100',
            'description' => 'required|string',
        ];
    }

    /** Column-keyed payload for Ticket::create(). reporterId/reporterRole are server-derived, never trusted from the body. */
    public function toModelAttributes(): array
    {
        $v = $this->validated();
        $map = [
            'unitId' => 'unit_id',
        ];
        $out = [];
        foreach ($v as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }
        return $out;
    }
}
