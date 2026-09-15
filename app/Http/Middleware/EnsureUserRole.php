<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserRole
{
    /**
     * Checks the user's role: 'role:driver', 'role:carrier,admin'
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'You do not have access to this area.',
            ], 403);
        }

        return $next($request);
    }
}
