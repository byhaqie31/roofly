<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\AnalyticsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function store(Request $request, AnalyticsRecorder $recorder): JsonResponse
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|max:255|unique:users,email',
            'phone'                 => 'nullable|string|max:30',
            'password'              => 'required|string|min:8|confirmed',
            'visitorId'             => 'nullable|uuid',
        ]);

        $user = User::create(array_merge(Arr::except($data, ['visitorId']), ['role' => UserRole::OWNER]));

        // The SPA authenticates with the Sanctum cookie session, not the token
        // below — so start that session for the new owner, replacing whatever
        // stale tenant/admin session the browser may still carry. Otherwise the
        // very next owner call (PATCH /account/onboarding) runs as the old user
        // and role:owner rejects it with 403.
        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $token = $user->createToken('api')->plainTextToken;

        $recorder->linkRegistration($user, $data['visitorId'] ?? null);

        return response()->json([
            'user'  => (new AuthUserResource($user))->resolve(),
            'token' => $token,
        ], 201);
    }
}
