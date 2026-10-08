<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'sometimes|string|max:255',
            'phone'        => 'sometimes|string|max:30',
            'businessName' => 'nullable|string|max:255',
        ];
    }

    public function toModelAttributes(): array
    {
        $v = $this->validated();
        $map = ['businessName' => 'business_name'];
        $out = [];
        foreach ($v as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }
        return $out;
    }
}
