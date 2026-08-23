<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class AnalyticsRangeRequest extends FormRequest
{
    public const MAX_DAYS = 366;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // The date_format/after_or_equal rules above may have already failed (e.g. an
            // unparsable "from") — range() calls Carbon::parse() and would throw on a value
            // that already failed date_format, turning a 422 into an uncaught 500. Bail out
            // once basic validation has already recorded an error.
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            [$from, $to] = $this->range();
            if ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
                $v->errors()->add('to', 'Range may not exceed ' . self::MAX_DAYS . ' days.');
            }
        });
    }

    /** @return array{0: Carbon, 1: Carbon} inclusive day bounds (start of from, end of to) */
    public function range(): array
    {
        $to = $this->filled('to') ? Carbon::parse($this->string('to')) : now();
        $from = $this->filled('from') ? Carbon::parse($this->string('from')) : $to->copy()->subDays(29);

        return [$from->startOfDay(), $to->endOfDay()];
    }
}
