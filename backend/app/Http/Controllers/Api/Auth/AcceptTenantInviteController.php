<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptTenantInviteRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\TenantInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Tenant accepts the emailed invite: sets a password, becomes `active`, is
 * logged in. Same `{user, token}` envelope as /auth/login and /auth/reset-password.
 */
class AcceptTenantInviteController extends Controller
{
    public function store(AcceptTenantInviteRequest $request): JsonResponse
    {
        $invite = TenantInvite::where('token_hash', hash('sha256', (string) $request->string('token')))
            ->with('user')
            ->first();
        $user = $invite?->user;

        if (
            $invite === null
            || ! $invite->isUsable()
            || $user === null
            || ! $user->isTenant()
            || strcasecmp($user->email, (string) $request->string('email')) !== 0
        ) {
            throw ValidationException::withMessages(['token' => 'This invite link is invalid or has expired.']);
        }

        $user->forceFill([
            'password'          => (string) $request->string('password'), // hashed by the model cast
            'status'            => 'active',
            'first_login_at'    => $user->first_login_at ?? now(),
            'email_verified_at' => $user->email_verified_at ?? now(), // they just proved they own the inbox
        ])->save();
        $invite->update(['accepted_at' => now()]);

        Auth::guard('web')->login($user); // pin the session guard, like the admin accept flow
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => (new AuthUserResource($user->fresh()))->resolve(),
            'token' => $token,
        ]);
    }
}
