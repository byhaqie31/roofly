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

        $user  = User::create(array_merge(Arr::except($data, ['visitorId']), ['role' => UserRole::OWNER]));
        $token = $user->createToken('api')->plainTextToken;

        $recorder->linkRegistration($user, $data['visitorId'] ?? null);

        return response()->json([
            'user'  => (new AuthUserResource($user))->resolve(),
            'token' => $token,
        ], 201);
    }
}
