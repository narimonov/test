<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Services\CarrierVerificationService;
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
    public function register(Request $request, CarrierVerificationService $carrierVerification)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', Rule::in([User::ROLE_DRIVER, User::ROLE_CARRIER])],
        ]);

        if ($data['role'] !== User::ROLE_CARRIER) {
            return $this->registerDriver($data);
        }

        // Kompaniya uchun MC yoki DOT raqamlardan hech bo'lmasa bittasi shart.
        // Alohida tekshiriladi, aks holda driver ro'yxatdan o'tishida ham talab qilinardi.
        $identifiers = $request->validate([
            'dot_number' => ['required_without:mc_number', 'nullable', 'string', 'max:20'],
            'mc_number'  => ['required_without:dot_number', 'nullable', 'string', 'max:20'],
        ]);

        return $this->registerCarrier($data + $identifiers, $carrierVerification);
    }

    /**
     * Kompaniya: avval FMCSA tekshiruvi, keyin akkaunt.
     *
     * Tasdiqlash kodi FMCSA'da ro'yxatdan o'tgan telefon/emailga yuboriladi —
     * foydalanuvchi kiritganiga emas. Shuning uchun begona odam kompaniya
     * nomidan ro'yxatdan o'ta olmaydi.
     */
    protected function registerCarrier(array $data, CarrierVerificationService $carrierVerification)
    {
        // Akkaunt yaratishdan oldin tekshiramiz — FMCSA rad qilsa hech narsa saqlanmaydi.
        $record = $carrierVerification->lookup($data['dot_number'] ?? null, $data['mc_number'] ?? null);
        $carrierVerification->assertNotAlreadyRegistered($record);

        if (! $record->hasContact()) {
            throw ValidationException::withMessages([
                'dot_number' => ['FMCSA bazasida bu kompaniya uchun telefon/email yo\'q. '
                    . 'Qo\'lda tekshiruv uchun support bilan bog\'laning.'],
            ]);
        }

        $carrier = DB::transaction(function () use ($data, $record, $carrierVerification) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role'     => User::ROLE_CARRIER,
            ]);

            $carrier = Carrier::create([
                'user_id'       => $user->id,
                'company_name'  => $record->displayName(),
                'contact_name'  => $data['name'],
                'contact_phone' => $data['phone'] ?? null,
            ]);

            return $carrierVerification->applyRecord($carrier, $record);
        });

        return response()->json([
            'token'    => $carrier->user->createToken('spa')->plainTextToken,
            'user'     => $this->userPayload($carrier->user->fresh()),
            'fmcsa'    => [
                'legal_name'         => $record->legalName,
                'dba_name'           => $record->dbaName,
                'dot_number'         => $record->dotNumber,
                'allowed_to_operate' => $record->allowedToOperate,
                'city'               => $record->city,
                'state'              => $record->state,
            ],
            // Kod qaysi kanalga yuborilishini foydalanuvchi tanlaydi.
            'channels' => $carrierVerification->availableChannels($carrier),
            'message'  => 'Kompaniya FMCSA bo\'yicha tasdiqlandi. Endi FMCSA\'dagi '
                . 'rasmiy kontaktga yuboriladigan kodni tasdiqlang.',
        ], 201);
    }

    protected function registerDriver(array $data)
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role'     => User::ROLE_DRIVER,
            ]);

            $parts = preg_split('/\s+/', trim($data['name']), 2);

            DriverProfile::create([
                'user_id'    => $user->id,
                'source'     => 'self_signup',
                'first_name' => $parts[0],
                'last_name'  => $parts[1] ?? '',
                'email'      => $data['email'],
                'phone'      => $data['phone'] ?? null,
            ]);

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

        if ($user->is_blocked) {
            return response()->json([
                'message' => 'Akkauntingiz bloklangan.' . ($user->blocked_reason ? ' Sabab: ' . $user->blocked_reason : ''),
                'code'    => 'account_blocked',
            ], 403);
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

    /**
     * Kompaniya uchun: kod qaysi FMCSA kontaktlariga yuborilishi mumkin.
     */
    public function carrierChannels(Request $request, CarrierVerificationService $service)
    {
        $carrier = $this->carrierOrFail($request);

        return response()->json([
            'channels'    => $service->availableChannels($carrier),
            'is_verified' => $carrier->is_fmcsa_verified,
            'company'     => [
                'legal_name'         => $carrier->fmcsa_legal_name,
                'dot_number'         => $carrier->dot_number,
                'allowed_to_operate' => $carrier->allowed_to_operate,
            ],
        ]);
    }

    /** Kompaniya uchun: kodni FMCSA kontaktiga yuborish. */
    public function sendCarrierCode(Request $request, CarrierVerificationService $service)
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['phone', 'email'])],
        ]);

        return response()->json([
            'message'      => 'Kod FMCSA\'da ro\'yxatdan o\'tgan kontaktga yuborildi.',
            'verification' => $service->issueCode($this->carrierOrFail($request), $data['channel']),
        ]);
    }

    /** Kompaniya uchun: kodni tasdiqlash. Shundan keyin ilova ochiladi. */
    public function verifyCarrierCode(Request $request, CarrierVerificationService $service)
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        $carrier = $service->confirmCode($this->carrierOrFail($request), $data['code']);

        return response()->json([
            'message' => 'Kompaniya tasdiqlandi.',
            'carrier' => $carrier,
            'user'    => $this->userPayload($request->user()->fresh()),
        ]);
    }

    // ------------------------------------------------------------------

    protected function carrierOrFail(Request $request): Carrier
    {
        $carrier = optional($request->user())->carrier;

        abort_unless($carrier, 403, 'Bu amal faqat kompaniya akkaunti uchun.');

        return $carrier;
    }

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
            'is_blocked'        => $user->is_blocked,
            'carrier'           => $user->carrier,
            'fmcsa_verified'    => $user->isCarrier() ? (bool) optional($user->carrier)->is_fmcsa_verified : null,
            'driver_profile_id' => optional($user->driverProfile)->id,
            'is_blacklisted'    => $user->isCarrier()
                ? (bool) optional($user->carrier)->is_blacklisted
                : (bool) optional($user->driverProfile)->is_blacklisted,
        ];
    }
}
