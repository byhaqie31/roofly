<?php
// backend/app/Http/Requests/Admin/WarnOwnerRequest.php
namespace App\Http\Requests\Admin;

use App\Notifications\OwnerWarning;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarnOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // can:owners.warn on the route
    }

    public function rules(): array
    {
        return [
            'template'  => ['required', Rule::in(OwnerWarning::TEMPLATES)],
            'suspendOn' => 'required|date_format:Y-m-d|after:today',
            'extraLine' => 'nullable|string|max:500',
        ];
    }
}
