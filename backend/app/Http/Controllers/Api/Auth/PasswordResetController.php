<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Customer forgot/reset password (spec 2026-08-23 § 3.4). The forgot endpoint
 * never reveals whether an email exists; admins are skipped because they
 * onboard through the invite flow.
 */
class PasswordResetController extends Controller
{
    private const GENERIC = 'If that email exists, we have sent a reset link.';

    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email']);

        // The admin exclusion MUST live inside sendResetLink's callback, not around it:
        // the whole method body (including the "no such user" branch) runs inside a
        // Laravel Timebox padded to config('auth.timebox_duration'), so every branch —
        // unknown email, admin email, real customer email — takes the same ~200ms.
        // Branching around the call (checking existence/role first, only calling
        // sendResetLink for eligible users) would reintroduce a timing oracle that
        // reveals which emails have an account, defeating this endpoint's purpose.
        Password::sendResetLink($data, function (User $user, string $token) {
            if ($user->isAdmin()) {
                Password::broker()->deleteToken($user); // don't leave a live token on an admin
                return Password::RESET_LINK_SENT;        // same status, no mail
            }
            $user->sendPasswordResetNotification($token);
        });

        return response()->json(['message' => self::GENERIC]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            if ($user->isAdmin()) {
                return; // admins never reset through the customer flow
            }
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete(); // revoke any tokens issued before this reset (likely reason for the reset)
            event(new PasswordReset($user));
        });

        $user = User::where('email', $data['email'])->first();
        if ($status !== Password::PASSWORD_RESET || $user === null || $user->isAdmin()) {
            return response()->json([
                'message' => 'This reset link is invalid or has expired.',
                'errors'  => ['email' => ['This reset link is invalid or has expired.']],
            ], 422);
        }

        Auth::login($user);
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
