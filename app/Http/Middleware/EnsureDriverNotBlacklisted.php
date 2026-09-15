<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * A blacklisted driver cannot apply, but can still see their own profile.
 */
class EnsureDriverNotBlacklisted
{
    public function handle(Request $request, Closure $next)
    {
        $profile = optional($request->user())->driverProfile;

        if ($profile && $profile->is_blacklisted) {
            return response()->json([
                'message' => 'Your account is blacklisted. You can submit an appeal.',
                'code'    => 'driver_blacklisted',
                'reason'  => $profile->blacklist_reason,
            ], 403);
        }

        return $next($request);
    }
}
