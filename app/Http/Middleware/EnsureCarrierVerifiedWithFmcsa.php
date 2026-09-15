<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * A carrier cannot use the app until it confirms the FMCSA code, and access
 * closes again if its authority later lapses.
 */
class EnsureCarrierVerifiedWithFmcsa
{
    public function handle(Request $request, Closure $next)
    {
        $carrier = optional($request->user())->carrier;

        if (! $carrier || $carrier->fmcsa_verified_at === null) {
            return response()->json([
                'message' => 'Verify your company with the FMCSA code.',
                'code'    => 'fmcsa_verification_required',
            ], 403);
        }

        if (! $carrier->allowed_to_operate) {
            return response()->json([
                'message' => 'FMCSA shows your company is not currently allowed to operate.',
                'code'    => 'fmcsa_not_active',
            ], 403);
        }

        if ($carrier->is_blocked) {
            return response()->json([
                'message' => 'This company account is blocked.' . ($carrier->blocked_reason ? ' Reason: ' . $carrier->blocked_reason : ''),
                'code'    => 'carrier_blocked',
            ], 403);
        }

        if ($carrier->is_blacklisted) {
            return response()->json([
                'message' => 'This company is blacklisted. You can submit an appeal.',
                'code'    => 'carrier_blacklisted',
                'reason'  => $carrier->blacklist_reason,
            ], 403);
        }

        return $next($request);
    }
}
