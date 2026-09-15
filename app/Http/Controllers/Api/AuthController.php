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
     * Sign-up. role = driver | carrier
     */
    public function register(Request $request, CarrierVerificationService $carrierVerification)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', Rule::in([User::ROLE_DRIVER, User::ROLE_CARRIER])],

            // Accepting the privacy notice is a condition of creating an account;
            // SMS consent is separate because the TCPA treats it separately.
            'privacy_accepted' => ['required', 'accepted'],
            'privacy_version'  => ['required', 'string'],
            'sms_consent'      => ['nullable', 'boolean'],
        ]);

        if ($data['role'] !== User::ROLE_CARRIER) {
            return $this->registerDriver($data);
        }

        // A carrier needs an MC or a DOT number. Validated separately, so the
        // rule does not fire for driver sign-ups.
        $identifiers = $request->validate([
            'dot_number' => ['required_without:mc_number', 'nullable', 'string', 'max:20'],
            'mc_number'  => ['required_without:dot_number', 'nullable', 'string', 'max:20'],
        ]);

        return $this->registerCarrier($data + $identifiers, $carrierVerification);
    }

    /**
     * Carrier: FMCSA check first, account second.
     *
     * The confirmation code goes to the phone or email FMCSA holds for the
     * carrier, not to whatever the user typed, so an outsider cannot open an
     * account in a company's name.
     */
    protected function registerCarrier(array $data, CarrierVerificationService $carrierVerification)
    {
        // Check before creating anything: if FMCSA says no, nothing is saved.
        $record = $carrierVerification->lookup($data['dot_number'] ?? null, $data['mc_number'] ?? null);
        $carrierVerification->assertNotAlreadyRegistered($record);

        if (! $record->hasContact()) {
            throw ValidationException::withMessages([
                'dot_number' => ['FMCSA has no phone or email on file for this carrier. '
                    . 'Contact support for a manual check.'],
            ]);
        }

        $carrier = DB::transaction(function () use ($data, $record, $carrierVerification) {
            $user = $this->createUser($data, User::ROLE_CARRIER);

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
            // The user picks which contact the code goes to.
            'channels' => $carrierVerification->availableChannels($carrier),
            'message'  => 'Company confirmed against FMCSA. Now confirm the code we send '
                . 'to the official contact FMCSA holds for it.',
        ], 201);
    }

    protected function registerDriver(array $data)
    {
        $user = DB::transaction(function () use ($data) {
            $user = $this->createUser($data, User::ROLE_DRIVER);

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
                'email' => ['Those credentials do not match.'],
            ]);
        }

        if ($user->is_blocked) {
            return response()->json([
                'message' => 'Your account is blocked.' . ($user->blocked_reason ? ' Reason: ' . $user->blocked_reason : ''),
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

        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /**
     * Send a verification code by phone or email.
     *
     * No SMS or email gateway is connected yet: the code is logged and, in
     * local environments, returned in the response. Connecting Twilio or SES
     * changes only this method.
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
            'message'      => 'Verification code sent.',
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
                'code' => ['That code is wrong or has expired.'],
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
            'message' => 'Verified.',
            'user'    => $this->userPayload($user->fresh()),
        ]);
    }

    /**
     * Which FMCSA contacts a carrier's code can be sent to.
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

    /** Send the carrier's code to the chosen FMCSA contact. */
    public function sendCarrierCode(Request $request, CarrierVerificationService $service)
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['phone', 'email'])],
        ]);

        return response()->json([
            'message'      => 'Code sent to the contact FMCSA holds for this carrier.',
            'verification' => $service->issueCode($this->carrierOrFail($request), $data['channel']),
        ]);
    }

    /** Confirm the carrier's code. The app opens after this. */
    public function verifyCarrierCode(Request $request, CarrierVerificationService $service)
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        $carrier = $service->confirmCode($this->carrierOrFail($request), $data['code']);

        return response()->json([
            'message' => 'Company verified.',
            'carrier' => $carrier,
            'user'    => $this->userPayload($request->user()->fresh()),
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * Creates the account and stores the consents given at sign-up, with the
     * IP they came from — a consent you cannot evidence is no consent.
     */
    protected function createUser(array $data, string $role): User
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role'     => $role,
        ]);

        $user->forceFill([
            'privacy_accepted_at' => now(),
            'privacy_version'     => $data['privacy_version'],
            'consent_ip'          => request()->ip(),
            'sms_consent_at'      => ! empty($data['sms_consent']) ? now() : null,
        ])->save();

        return $user->fresh();
    }

    protected function carrierOrFail(Request $request): Carrier
    {
        $carrier = optional($request->user())->carrier;

        abort_unless($carrier, 403, 'This is only available to company accounts.');

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
            // Returned until a gateway is connected, otherwise it cannot be tested.
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

            // Consent state, so the app can prompt when the policy moves on.
            'privacy_version'             => $user->privacy_version,
            'has_current_privacy_consent' => $user->has_current_privacy_consent,
            'sms_consent_at'              => $user->sms_consent_at,
            'mvr_consent_at'              => $user->mvr_consent_at,
        ];
    }
}
