<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureVerified
{
    /**
     * Requires a confirmed phone or email.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->is_verified) {
            return response()->json([
                'message' => 'Confirm your phone or email address first.',
                'code'    => 'verification_required',
            ], 403);
        }

        return $next($request);
    }
}
