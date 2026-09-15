<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCarrierSubscribed
{
    /**
     * The carrier side is paid: without an active plan the driver pool is closed.
     */
    public function handle(Request $request, Closure $next)
    {
        $carrier = optional($request->user())->carrier;

        if (! $carrier || ! $carrier->has_active_subscription) {
            return response()->json([
                'message' => 'An active subscription is required to use the driver pool.',
                'code'    => 'subscription_required',
            ], 402);
        }

        return $next($request);
    }
}
