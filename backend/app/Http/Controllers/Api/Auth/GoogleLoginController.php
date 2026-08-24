<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\GoogleIdToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Owner-only Google sign-in (spec 2026-08-23 § 3.3). The SPA posts the GIS
 * ID token; we verify it, auto-link by verified email, and start the same
 * session + token pair `/auth/login` does.
 */
class GoogleLoginController extends Controller
{
    public function store(Request $request, GoogleIdToken $google, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['credential' => 'required|string']);

        $profile = $google->verify($data['credential']);
        if ($profile === null) {
            return response()->json(['message' => 'Google sign-in failed.'], 401);
        }

        $user = User::where('email', $profile['email'])->first();
        $created = false;

        if ($user !== null && $user->role !== UserRole::OWNER) {
            return response()->json([
                'message' => 'This email is not registered as an owner account.',
                'code'    => 'not_owner',
            ], 403);
        }

        if ($user === null) {
            $user = User::create([
                'name'       => $profile['name'],
                'email'      => $profile['email'],
                'role'       => UserRole::OWNER,
                'password'   => null,
                'google_id'  => $profile['sub'],
                'avatar_url' => $profile['picture'],
            ]);
            // email_verified_at isn't mass-assignable; a Google-verified
            // address is trusted the same way an admin-created account is.
            $user->forceFill(['email_verified_at' => now()])->save();
            $created = true;
        } else {
            $user->forceFill([
                'google_id'         => $user->google_id ?? $profile['sub'],
                'avatar_url'        => $user->avatar_url ?? $profile['picture'],
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        if ($user->first_login_at === null) {
            $user->forceFill(['first_login_at' => now()])->saveQuietly();
        }

        $audit->record($created ? AuditLogger::AUTH_GOOGLE_REGISTER : AuditLogger::AUTH_GOOGLE_LOGIN, $user);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => (new AuthUserResource($user->fresh()))->resolve(),
            'token' => $token,
        ], $created ? 201 : 200);
    }
}
