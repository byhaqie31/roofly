<?php
// backend/app/Http/Controllers/Api/Admin/AcceptInviteController.php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcceptInviteRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\AdminInvite;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AcceptInviteController extends Controller
{
    public function store(AcceptInviteRequest $request, AuditLogger $audit): JsonResponse
    {
        $invite = AdminInvite::where('token_hash', hash('sha256', $request->string('token')))->first();

        if ($invite === null || ! $invite->isUsable() || ! $invite->user?->isAdmin() || $invite->user->isDisabled()) {
            throw ValidationException::withMessages(['token' => 'This invite link is invalid or has expired.']);
        }

        $user = $invite->user;
        $user->forceFill([
            'password'       => Hash::make($request->string('password')),
            'first_login_at' => $user->first_login_at ?? now(),
        ])->save();
        $invite->update(['accepted_at' => now()]);

        Auth::guard('web')->login($user);
        $audit->record(AuditLogger::ADMIN_INVITE_ACCEPTED, $user);

        return response()->json(['user' => (new AuthUserResource($user))->resolve()]);
    }
}
