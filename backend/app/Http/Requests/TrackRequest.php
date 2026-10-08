<?php

namespace App\Http\Requests;

use App\Models\AnalyticsEvent;
use App\Services\AnalyticsRecorder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'visitorId'    => 'required|uuid',
            'event'        => ['required', Rule::in(AnalyticsEvent::EVENTS)],
            'path'         => 'nullable|string|max:255',
            'referrer'     => 'nullable|string|max:255',
            'utm'          => 'nullable|array:source,medium,campaign',
            'utm.*'        => 'nullable|string|max:100',
            'props'        => ['nullable', 'array', fn ($attr, $value, $fail) => strlen(json_encode($value)) > AnalyticsRecorder::MAX_PROPS_BYTES ? $fail('props too large') : null],
            'props.email'  => 'nullable|email|max:255',
            'props.userId' => 'nullable|uuid',
            'props.role'   => 'nullable|string|max:20',
            'at'           => 'nullable|date',
        ];
    }
}
