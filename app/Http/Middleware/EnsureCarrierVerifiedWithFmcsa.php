<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Kompaniya FMCSA kodi bilan tasdiqlanmaguncha ilovadan foydalana olmaydi.
 * Authority keyinchalik to'xtatilsa ham kirish yopiladi.
 */
class EnsureCarrierVerifiedWithFmcsa
{
    public function handle(Request $request, Closure $next)
    {
        $carrier = optional($request->user())->carrier;

        if (! $carrier || $carrier->fmcsa_verified_at === null) {
            return response()->json([
                'message' => 'Kompaniyangizni FMCSA kodi bilan tasdiqlang.',
                'code'    => 'fmcsa_verification_required',
            ], 403);
        }

        if (! $carrier->allowed_to_operate) {
            return response()->json([
                'message' => 'FMCSA bo\'yicha kompaniyangiz hozir faol emas (authority to\'xtatilgan).',
                'code'    => 'fmcsa_not_active',
            ], 403);
        }

        if ($carrier->is_blocked) {
            return response()->json([
                'message' => 'Kompaniya akkaunti bloklangan.' . ($carrier->blocked_reason ? ' Sabab: ' . $carrier->blocked_reason : ''),
                'code'    => 'carrier_blocked',
            ], 403);
        }

        if ($carrier->is_blacklisted) {
            return response()->json([
                'message' => 'Kompaniya blacklist\'da. Apelyatsiya berishingiz mumkin.',
                'code'    => 'carrier_blacklisted',
                'reason'  => $carrier->blacklist_reason,
            ], 403);
        }

        return $next($request);
    }
}
