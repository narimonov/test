<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Blacklist'dagi driver ariza bera olmaydi (profilini ko'rishi mumkin).
 */
class EnsureDriverNotBlacklisted
{
    public function handle(Request $request, Closure $next)
    {
        $profile = optional($request->user())->driverProfile;

        if ($profile && $profile->is_blacklisted) {
            return response()->json([
                'message' => 'Siz blacklist\'dasiz. Apelyatsiya berishingiz mumkin.',
                'code'    => 'driver_blacklisted',
                'reason'  => $profile->blacklist_reason,
            ], 403);
        }

        return $next($request);
    }
}
