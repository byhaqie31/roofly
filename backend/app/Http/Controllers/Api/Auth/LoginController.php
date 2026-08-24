<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $existing = User::where('email', $credentials['email'])->first();
        if ($existing !== null && $existing->isOwner() && ! $existing->hasPassword()) {
            return response()->json([
                'message' => 'This account signs in with Google.',
                'errors'  => ['email' => ['This account signs in with Google.']],
            ], 422);
        }

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = Auth::user();

        // Admins use the Admin Portal (POST /api/admin/auth/login); the
        // customer form is never a back door into the back office.
        if ($user->isAdmin()) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->first_login_at === null) {
            $user->forceFill(['first_login_at' => now()])->saveQuietly();
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => (new AuthUserResource($user))->resolve(),
            'token' => $token,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(new AuthUserResource($request->user()));
    }
}
