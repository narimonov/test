<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Ro'yxatdan o'tish. role = driver | carrier
     * Driver bo'lsa bo'sh profil, carrier bo'lsa kompaniya yozuvi yaratiladi.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'        => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'role'         => ['required', Rule::in([User::ROLE_DRIVER, User::ROLE_CARRIER])],
            'company_name' => ['required_if:role,carrier', 'nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role'     => $data['role'],
            ]);

            if ($user->isCarrier()) {
                Carrier::create([
                    'user_id'      => $user->id,
                    'company_name' => $data['company_name'],
                    'contact_name' => $data['name'],
                    'contact_phone' => $data['phone'] ?? null,
                ]);
            } else {
                $parts = preg_split('/\s+/', trim($data['name']), 2);

                DriverProfile::create([
                    'user_id'    => $user->id,
                    'source'     => 'self_signup',
                    'first_name' => $parts[0],
                    'last_name'  => $parts[1] ?? '',
                    'email'      => $data['email'],
                    'phone'      => $data['phone'] ?? null,
                ]);
            }

            return $user;
        });

        $verification = $this->issueCode($user, $user->phone ? 'phone' : 'email');

        return response()->json([
            'token'        => $user->createToken('spa')->plainTextToken,
            'user'         => $this->userPayload($user->fresh()),
            'verification' => $verification,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email yoki parol noto\'g\'ri.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('spa')->plainTextToken,
            'user'  => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Chiqildi.']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /**
     * Tasdiqlash kodini yuborish (phone yoki email).
     *
     * Hozircha haqiqiy SMS/email gateway ulanmagan — kod log'ga yoziladi va
     * local muhitda javobda qaytariladi. Twilio/SES ulanganda shu joy o'zgaradi.
     */
    public function sendCode(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['phone', 'email'])],
            'phone'   => ['required_if:channel,phone', 'nullable', 'string', 'max:30'],
        ]);

        $user = $request->user();

        if ($data['channel'] === 'phone' && ! empty($data['phone'])) {
            $request->validate(['phone' => Rule::unique('users', 'phone')->ignore($user->id)]);
            $user->phone = $data['phone'];
            $user->save();
        }

        return response()->json([
            'message'      => 'Tasdiqlash kodi yuborildi.',
            'verification' => $this->issueCode($user, $data['channel']),
        ]);
    }

    public function verifyCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        $expired = $user->verification_code_expires_at === null
            || $user->verification_code_expires_at->isPast();

        if ($user->verification_code === null || $expired || ! hash_equals($user->verification_code, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => ['Kod noto\'g\'ri yoki muddati tugagan.'],
            ]);
        }

        if ($user->verification_channel === 'phone') {
            $user->phone_verified_at = now();
        } else {
            $user->email_verified_at = now();
        }

        $user->verification_code = null;
        $user->verification_code_expires_at = null;
        $user->save();

        return response()->json([
            'message' => 'Tasdiqlandi.',
            'user'    => $this->userPayload($user->fresh()),
        ]);
    }

    // ------------------------------------------------------------------

    protected function issueCode(User $user, string $channel): array
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'verification_code'            => $code,
            'verification_channel'         => $channel,
            'verification_code_expires_at' => now()->addMinutes(15),
        ])->save();

        Log::info('Verification code issued', [
            'user_id' => $user->id,
            'channel' => $channel,
            'code'    => $code,
        ]);

        return array_filter([
            'channel'    => $channel,
            'sent_to'    => $channel === 'phone' ? $user->phone : $user->email,
            'expires_in' => 15 * 60,
            // Gateway ulanmaguncha kodni qaytaramiz, aks holda test qilib bo'lmaydi.
            'debug_code' => config('app.debug') ? $code : null,
        ], fn ($value) => $value !== null);
    }

    protected function userPayload(User $user): array
    {
        $user->loadMissing(['carrier', 'driverProfile']);

        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'phone'             => $user->phone,
            'role'              => $user->role,
            'is_verified'       => $user->is_verified,
            'email_verified'    => $user->email_verified_at !== null,
            'phone_verified'    => $user->phone_verified_at !== null,
            'carrier'           => $user->carrier,
            'driver_profile_id' => optional($user->driverProfile)->id,
        ];
    }
}
