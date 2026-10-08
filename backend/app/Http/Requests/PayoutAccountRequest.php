<?php

namespace App\Http\Requests;

use App\Enums\DuitNowIdType;
use App\Enums\MalaysianBank;
use App\Models\PayoutAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared rules for creating / editing a payout account (spec 2026-10-08 § 3.1, § 4).
 * Cross-field rules are checked against the merged state (stored row + request),
 * so a PATCH that only touches one field can't leave the account invalid.
 */
abstract class PayoutAccountRequest extends FormRequest
{
    /** 'required' on create, 'sometimes' on update. */
    abstract protected function presence(): string;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $p = $this->presence();

        return [
            'label'             => "{$p}|string|max:60",
            'bank'              => [$p, 'string', Rule::in(MalaysianBank::values())],
            'accountHolderName' => "{$p}|string|max:120",
            // Spaces / dashes allowed on input, stored as digits only.
            'accountNumber'     => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9 \-]+$/'],
            'duitnowIdType'     => ['sometimes', 'nullable', 'string', Rule::in(DuitNowIdType::values())],
            'duitnowId'         => 'sometimes|nullable|string|max:40',
            'isDefault'         => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'accountNumber.regex' => 'The account number may only contain digits, spaces and dashes.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $merged = $this->mergedState();

            if ($merged['account_number'] === null && $merged['duitnow_id'] === null) {
                $validator->errors()->add('accountNumber', 'Enter an account number or a DuitNow ID.');
            }
            if ($merged['duitnow_id'] !== null && $merged['duitnow_id_type'] === null) {
                $validator->errors()->add('duitnowIdType', 'Choose the DuitNow ID type.');
            }
            if ($merged['duitnow_id_type'] !== null && $merged['duitnow_id'] === null) {
                $validator->errors()->add('duitnowId', 'Enter the DuitNow ID.');
            }
        }];
    }

    /** Column-keyed payload for PayoutAccount::create()/update(). `isDefault` is handled by the controller. */
    public function toModelAttributes(): array
    {
        return $this->toColumns($this->validated());
    }

    private function toColumns(array $v): array
    {
        $map = [
            'label'             => 'label',
            'bank'              => 'bank',
            'accountHolderName' => 'account_holder_name',
            'accountNumber'     => 'account_number',
            'duitnowIdType'     => 'duitnow_id_type',
            'duitnowId'         => 'duitnow_id',
        ];
        $out = [];
        foreach ($map as $key => $column) {
            if (array_key_exists($key, $v)) {
                $out[$column] = $key === 'accountNumber'
                    ? PayoutAccount::normaliseAccountNumber($v[$key])
                    : (is_string($v[$key]) ? trim($v[$key]) : $v[$key]);
            }
        }

        return $out;
    }

    /** @return array{account_number: ?string, duitnow_id: ?string, duitnow_id_type: ?string} */
    private function mergedState(): array
    {
        /** @var PayoutAccount|null $existing */
        $existing = $this->route('payoutAccount');
        // Rules already passed when this runs, so the raw input is well-formed.
        $incoming = $this->toColumns($this->only(['accountNumber', 'duitnowIdType', 'duitnowId']));
        $pick = function (string $column) use ($incoming, $existing): ?string {
            $value = array_key_exists($column, $incoming) ? $incoming[$column] : $existing?->{$column};

            return $value === '' ? null : $value;
        };

        return [
            'account_number'  => $pick('account_number'),
            'duitnow_id'      => $pick('duitnow_id'),
            'duitnow_id_type' => $pick('duitnow_id_type'),
        ];
    }
}
