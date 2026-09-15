<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserRole
{
    /**
     * Foydalanuvchi rolini tekshiradi: 'role:driver', 'role:carrier,admin'
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Bu bo\'limga ruxsat yo\'q.',
            ], 403);
        }

        return $next($request);
    }
}
