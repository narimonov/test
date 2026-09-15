<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCarrierSubscribed
{
    /**
     * Carrier tomoni pullik — obuna aktiv bo'lmasa driver bazasi yopiq.
     */
    public function handle(Request $request, Closure $next)
    {
        $carrier = optional($request->user())->carrier;

        if (! $carrier || ! $carrier->has_active_subscription) {
            return response()->json([
                'message' => 'Driver bazasidan foydalanish uchun aktiv obuna kerak.',
                'code'    => 'subscription_required',
            ], 402);
        }

        return $next($request);
    }
}
