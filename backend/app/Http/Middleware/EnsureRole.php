<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role guard reading the users.role enum column directly.
 * (Spatie Permission stays installed for future granular permissions,
 * but nothing is wired to it yet — one role system, not two.)
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        abort_if(! $user || $user->role->value !== $role, 403);

        return $next($request);
    }
}
