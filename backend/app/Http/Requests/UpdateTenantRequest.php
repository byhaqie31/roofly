<?php

namespace App\Http\Requests;

use App\Support\MyKad;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'sometimes|string|max:255',
            'email'            => 'sometimes|email|max:255|unique:users,email,' . $this->route('tenant')->id,
            'phone'            => 'sometimes|string|max:30',
            'status'           => 'sometimes|in:invited,active,notice_given,moved_out',
            'personal'         => 'nullable|array',   // camelCase interior stored verbatim
            'emergencyContact' => 'nullable|array',
        ];
    }

    /** MyKad: accept bare digits, store dashed; default date of birth from it. */
    protected function prepareForValidation(): void
    {
        $personal = $this->input('personal');
        if (is_array($personal)) {
            $this->merge(['personal' => MyKad::applyToPersonal($personal)]);
        }
    }

    public function toModelAttributes(): array
    {
        $v = $this->validated();
        $map = ['personal' => 'personal_info', 'emergencyContact' => 'emergency_contact'];
        $out = [];
        foreach ($v as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }
        return $out;
    }
}
