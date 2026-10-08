<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Notifications\AdminNewSupportEnquiry;
use App\Support\SuperAdminAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The in-app help button (owners + tenants). Deliberately outside the owner
 * group's not-suspended guard: a suspended owner must still be able to reach us.
 */
class SupportEnquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::OWNER, UserRole::TENANT], true), 403);

        $data = $request->validate([
            'type'    => ['required', Rule::in(Enquiry::TYPES)],
            'message' => 'required|string|min:5|max:5000',
            'pageUrl' => 'nullable|string|max:500',
            'pageLabel' => 'nullable|string|max:120',
        ]);

        $enquiry = Enquiry::create([
            'user_id'    => $user->id,
            'name'       => (string) $user->name,
            'email'      => $user->email,
            'role'       => $user->role->value,
            'type'       => $data['type'],
            'message'    => trim($data['message']),
            'page_url'   => $data['pageUrl'] ?? null,
            'page_label' => $data['pageLabel'] ?? null,
            'user_agent' => Str::limit((string) $request->userAgent(), 497),
            'status'     => 'new',
        ]);

        SuperAdminAlerts::send(AdminNewSupportEnquiry::fromEnquiry($enquiry));

        return response()->json(['id' => $enquiry->id], 201);
    }
}
