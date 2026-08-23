<?php
// backend/app/Http/Requests/Admin/SuspendOwnerRequest.php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SuspendOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // can:owners.suspend on the route
    }

    public function rules(): array
    {
        return ['reason' => 'required|string|min:10|max:1000'];
    }
}
