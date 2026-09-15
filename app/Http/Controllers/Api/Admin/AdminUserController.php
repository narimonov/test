<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q'       => ['nullable', 'string', 'max:100'],
            'role'    => ['nullable', Rule::in([User::ROLE_DRIVER, User::ROLE_CARRIER, User::ROLE_ADMIN])],
            'blocked' => ['nullable', 'boolean'],
        ]);

        $users = User::query()
            ->with(['carrier:id,user_id,company_name,dot_number,fmcsa_verified_at,blacklisted_at,blocked_at'])
            ->when($filters['q'] ?? null, function ($query, $term) {
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when(isset($filters['blocked']), function ($query) use ($filters) {
                $filters['blocked']
                    ? $query->whereNotNull('blocked_at')
                    : $query->whereNull('blocked_at');
            })
            ->latest()
            ->paginate(25);

        return response()->json($users);
    }

    /** Foydalanuvchini bloklash — barcha tokenlari ham bekor qilinadi. */
    public function block(Request $request, User $user)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($user->isAdmin()) {
            return response()->json(['message' => 'Admin akkauntini bloklab bo\'lmaydi.'], 422);
        }

        $user->forceFill([
            'blocked_at'         => now(),
            'blocked_reason'     => $data['reason'],
            'blocked_by_user_id' => $request->user()->id,
        ])->save();

        // Bloklangan foydalanuvchi ochiq sessiyalar orqali ishlashda davom etmasin.
        $user->tokens()->delete();

        return response()->json(['message' => 'Foydalanuvchi bloklandi.', 'user' => $user->fresh()]);
    }

    public function unblock(User $user)
    {
        $user->forceFill([
            'blocked_at'         => null,
            'blocked_reason'     => null,
            'blocked_by_user_id' => null,
        ])->save();

        return response()->json(['message' => 'Blok olib tashlandi.', 'user' => $user->fresh()]);
    }
}
