<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MagicLinkController extends Controller
{
    /** Send a magic-link invite to a tenant email. Phase 2 feature. */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // TODO Phase 2: generate signed URL, dispatch TenantInviteNotification
        return response()->json(['message' => 'Magic link feature coming in Phase 2.'], 501);
    }

    /** Authenticate via magic-link token. Phase 2 feature. */
    public function authenticate(string $token): JsonResponse
    {
        // TODO Phase 2: verify signed token, issue Sanctum token
        return response()->json(['message' => 'Magic link feature coming in Phase 2.'], 501);
    }
}
