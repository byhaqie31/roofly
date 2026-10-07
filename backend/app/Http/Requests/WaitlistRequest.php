<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Coming-soon waitlist signup. `website` is a honeypot: humans never see it, bots fill it. */
class WaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email'     => 'required|email|max:255',
            'visitorId' => 'nullable|uuid',
            'website'   => 'nullable|string|max:255',
        ];
    }
}
