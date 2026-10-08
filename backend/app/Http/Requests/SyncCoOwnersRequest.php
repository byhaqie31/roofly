<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncCoOwnersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coOwners'              => 'required|array|min:1',
            'coOwners.*.id'         => 'nullable|string',
            'coOwners.*.name'       => 'required|string|max:255',
            'coOwners.*.sharePct'   => 'required|numeric|min:0.01|max:100',
            'coOwners.*.isPrimary'  => 'required|boolean',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach (self::coOwnerInvariantErrors($this->input('coOwners', [])) as $message) {
                    $validator->errors()->add('coOwners', $message);
                }
            },
        ];
    }

    /**
     * Shared co-owner invariant checks (sum=100, exactly one primary), reused by
     * UpdatePropertyRequest so the two entry points (PATCH /properties/{id} and
     * PUT /properties/{id}/co-owners) enforce identical rules.
     *
     * @param array<int, array{sharePct?: mixed, isPrimary?: mixed}> $rows
     * @return string[] error messages, empty when the rows are valid
     */
    public static function coOwnerInvariantErrors(array $rows): array
    {
        $errors = [];
        $total = array_sum(array_column($rows, 'sharePct'));
        if (abs($total - 100) > 0.01) {
            $errors[] = 'Co-owner shares must sum to 100%.';
        }
        if (count(array_filter($rows, fn ($c) => $c['isPrimary'] ?? false)) !== 1) {
            $errors[] = 'Exactly one co-owner must be marked as primary.';
        }
        return $errors;
    }

    /** Rows keyed for property_co_owners columns. */
    public function toRows(): array
    {
        return array_map(fn ($c) => [
            'name'       => $c['name'],
            'share_pct'  => $c['sharePct'],
            'is_primary' => $c['isPrimary'],
        ], $this->validated()['coOwners']);
    }
}
