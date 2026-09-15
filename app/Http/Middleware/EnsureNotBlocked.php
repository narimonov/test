<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * A user blocked by an admin cannot do anything.
 */
class EnsureNotBlocked
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->is_blocked) {
            return response()->json([
                'message' => 'Your account is blocked.' . ($user->blocked_reason ? ' Reason: ' . $user->blocked_reason : ''),
                'code'    => 'account_blocked',
            ], 403);
        }

        return $next($request);
    }
}
