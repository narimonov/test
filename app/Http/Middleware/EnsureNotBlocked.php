<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Admin tomonidan bloklangan foydalanuvchi hech qanday amal bajara olmaydi.
 */
class EnsureNotBlocked
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->is_blocked) {
            return response()->json([
                'message' => 'Akkauntingiz bloklangan.' . ($user->blocked_reason ? ' Sabab: ' . $user->blocked_reason : ''),
                'code'    => 'account_blocked',
            ], 403);
        }

        return $next($request);
    }
}
