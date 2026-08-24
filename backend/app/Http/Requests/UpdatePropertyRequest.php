<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'sometimes|string|max:255',
            'internalLabel' => 'nullable|string|max:255',
            'type'          => 'sometimes|in:condo,landed,shoplot,room',
            'purpose'       => ['sometimes', Rule::in(\App\Enums\PropertyPurpose::values())],
            'notes'         => 'nullable|string',
            'address'       => 'sometimes|string|max:500',
            'city'          => 'sometimes|string|max:100',
            'state'         => ['sometimes', Rule::in(StorePropertyRequest::MY_STATES)],
            'postcode'      => 'sometimes|digits:5',
            'yearBuilt'     => 'nullable|integer|min:1900|max:2100',
            'builtUpSqft'   => 'nullable|integer|min:1',
            'landSqft'      => 'nullable|integer|min:1',
            'bedrooms'      => 'nullable|integer|min:0|max:20',
            'bathrooms'     => 'nullable|integer|min:0|max:20',
            'parkingLots'   => 'nullable|integer|min:0',
            'furnishing'    => 'nullable|in:unfurnished,partial,fully',
            'ownership'     => 'nullable|array',   // camelCase interior stored verbatim
            'utilities'     => 'nullable|array',
            // Optional co-owner sync — mirrors SyncCoOwnersRequest's per-row rules but
            // `sometimes` since PATCH /properties/{id} may omit coOwners entirely.
            'coOwners'             => 'sometimes|array|min:1',
            'coOwners.*.id'        => 'nullable|string',
            'coOwners.*.name'      => 'required|string|max:255',
            'coOwners.*.sharePct'  => 'required|numeric|min:0.01|max:100',
            'coOwners.*.isPrimary' => 'required|boolean',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->has('coOwners')) {
                    return;
                }
                foreach (SyncCoOwnersRequest::coOwnerInvariantErrors($this->input('coOwners', [])) as $message) {
                    $validator->errors()->add('coOwners', $message);
                }
            },
        ];
    }

    public function toModelAttributes(): array
    {
        $v = $this->validated();
        unset($v['coOwners']); // not a properties column — synced separately by the controller

        $map = [
            'internalLabel' => 'internal_label',
            'yearBuilt'     => 'year_built',
            'builtUpSqft'   => 'built_up_sqft',
            'landSqft'      => 'land_sqft',
            'parkingLots'   => 'parking_lots',
        ];
        $out = [];
        foreach ($v as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }
        return $out;
    }

    /** Rows keyed for property_co_owners columns, or null when coOwners wasn't sent. */
    public function toCoOwnerRows(): ?array
    {
        if (! $this->has('coOwners')) {
            return null;
        }

        return array_map(fn ($c) => [
            'name'       => $c['name'],
            'share_pct'  => $c['sharePct'],
            'is_primary' => $c['isPrimary'],
        ], $this->validated()['coOwners']);
    }
}
