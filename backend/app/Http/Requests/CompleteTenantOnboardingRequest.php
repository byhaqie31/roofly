<?php

namespace App\Http\Requests;

use App\Support\MyKad;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Tenant onboarding submit (spec 2026-10-07 § 4.4). Same payload shape as the
 * profile PATCH, but the core fields the landlord needs for the agreement are
 * required: phone, MyKad number, emergency contact name + phone.
 */
class CompleteTenantOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                          => 'sometimes|string|max:255',
            'phone'                         => 'required|string|max:30',
            'personal'                      => 'required|array',
            'personal.icNumber'             => ['required', 'string', 'regex:/^\d{6}-\d{2}-\d{4}$/'],
            'personal.dateOfBirth'          => 'nullable|date_format:Y-m-d',
            'personal.occupation'           => 'nullable|string|max:100',
            'personal.employer'             => 'nullable|string|max:100',
            'personal.monthlyIncome'        => 'nullable|integer|min:0',
            'personal.nationality'          => 'nullable|string|max:50',
            'emergencyContact'              => 'required|array',
            'emergencyContact.name'         => 'required|string|max:80',
            'emergencyContact.phone'        => 'required|string|max:30',
            'emergencyContact.relationship' => 'nullable|string|max:50',
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

    public function messages(): array
    {
        return ['personal.icNumber.regex' => 'Use the MyKad format YYMMDD-PB-####.'];
    }

    /** Column-keyed payload; nested camelCase is stored verbatim like the profile PATCH. */
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
