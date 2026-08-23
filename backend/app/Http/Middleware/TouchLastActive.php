<?php
// backend/app/Http/Middleware/TouchLastActive.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Heartbeat for owners, tenants and admins — at most one write per 10 min. */
class TouchLastActive
{
    public const THROTTLE_MINUTES = 10;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ($user->last_active_at === null || $user->last_active_at->lt(now()->subMinutes(self::THROTTLE_MINUTES)))) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
