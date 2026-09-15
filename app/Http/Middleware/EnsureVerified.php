<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureVerified
{
    /**
     * Telefon yoki email tasdiqlangan bo'lishi shart.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->is_verified) {
            return response()->json([
                'message' => 'Avval telefon yoki email manzilingizni tasdiqlang.',
                'code'    => 'verification_required',
            ], 403);
        }

        return $next($request);
    }
}
