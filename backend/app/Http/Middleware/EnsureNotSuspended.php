<?php
// backend/app/Http/Middleware/EnsureNotSuspended.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a suspended owner from the owner API (spec § 8). Applied to the
 * role:owner group only — the owner's tenants keep working, and /auth/me
 * still answers so the frontend can show /suspended instead of "bad login".
 */
class EnsureNotSuspended
{
    public const CODE = 'account_suspended';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->isSuspended()) {
            return response()->json([
                'code'    => self::CODE,
                'message' => 'This account has been suspended. Please contact support.',
            ], 403);
        }

        return $next($request);
    }
}
