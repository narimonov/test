<?php

namespace App\Services;

use App\Models\Carrier;
use App\Services\Fmcsa\CarrierRecord;
use App\Services\Fmcsa\FmcsaClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Checks a company against FMCSA and sends the confirmation code to the
 * contact FMCSA holds for it.
 *
 * The point is that the code never goes to an address the user typed. Only
 * someone who already controls the carrier's registered contact can open the
 * account.
 */
class CarrierVerificationService
{
    /** @var FmcsaClient */
    protected $fmcsa;

    public function __construct(FmcsaClient $fmcsa)
    {
        $this->fmcsa = $fmcsa;
    }

    /**
     * Look the carrier up by MC or DOT and check the result.
     *
     * @throws ValidationException when it is not found or not operating
     */
    public function lookup(?string $dotNumber, ?string $mcNumber): CarrierRecord
    {
        $record = null;

        if ($dotNumber) {
            $record = $this->fmcsa->findByDotNumber($dotNumber);
        }

        if (! $record && $mcNumber) {
            $record = $this->fmcsa->findByDocketNumber($mcNumber);
        }

        if (! $record) {
            throw ValidationException::withMessages([
                'dot_number' => ['FMCSA has no record of that MC or DOT number. Check the number.'],
            ]);
        }

        if (config('fmcsa.require_active_status') && ! $record->isOperational()) {
            throw ValidationException::withMessages([
                'dot_number' => ['FMCSA shows this carrier is not allowed to operate — the authority is inactive or out of service.'],
            ]);
        }

        return $record;
    }

    /**
     * A DOT number can only back one account.
     *
     * @throws ValidationException
     */
    public function assertNotAlreadyRegistered(CarrierRecord $record, ?int $ignoreCarrierId = null): void
    {
        $exists = Carrier::query()
            ->where('dot_number', $record->dotNumber)
            ->whereNotNull('fmcsa_verified_at')
            ->when($ignoreCarrierId, fn ($query) => $query->where('id', '!=', $ignoreCarrierId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'dot_number' => ['An account already exists for this DOT number. Ask your company admin for access.'],
            ]);
        }
    }

    /** Write the FMCSA record onto the carrier. */
    public function applyRecord(Carrier $carrier, CarrierRecord $record): Carrier
    {
        $carrier->forceFill([
            'dot_number'         => $record->dotNumber,
            'mc_number'          => $record->docketNumber ?: $carrier->mc_number,
            'fmcsa_legal_name'   => $record->legalName,
            'fmcsa_dba_name'     => $record->dbaName,
            'fmcsa_status'       => $record->statusCode,
            'allowed_to_operate' => $record->allowedToOperate,
            'fmcsa_phone'        => $record->phone,
            'fmcsa_email'        => $record->email,
            'fmcsa_checked_at'   => now(),
            'fmcsa_snapshot'     => $record->toArray(),
            'city'               => $carrier->city ?: $record->city,
            'state'              => $carrier->state ?: $record->state,
        ])->save();

        return $carrier;
    }

    /**
     * The contacts a code can be sent to, masked.
     *
     * @return array<int, array{channel: string, masked: string}>
     */
    public function availableChannels(Carrier $carrier): array
    {
        $channels = [];

        if ($carrier->fmcsa_phone) {
            $channels[] = ['channel' => 'phone', 'masked' => CarrierRecord::mask($carrier->fmcsa_phone)];
        }

        if ($carrier->fmcsa_email) {
            $channels[] = ['channel' => 'email', 'masked' => CarrierRecord::mask($carrier->fmcsa_email, true)];
        }

        return $channels;
    }

    /**
     * Send the confirmation code to the FMCSA contact.
     *
     * No SMS or email gateway is connected yet: the code is logged, and
     * returned in the response while APP_DEBUG is on.
     */
    public function issueCode(Carrier $carrier, string $channel): array
    {
        $destination = $channel === 'phone' ? $carrier->fmcsa_phone : $carrier->fmcsa_email;

        if (! $destination) {
            throw ValidationException::withMessages([
                'channel' => ['FMCSA has no contact on file for that channel.'],
            ]);
        }

        $code = (string) random_int(100000, 999999);

        $carrier->user->forceFill([
            'verification_code'            => $code,
            'verification_channel'         => $channel,
            'verification_code_expires_at' => now()->addMinutes(15),
        ])->save();

        Log::info('FMCSA verification code issued', [
            'carrier_id'  => $carrier->id,
            'dot_number'  => $carrier->dot_number,
            'channel'     => $channel,
            'destination' => $destination,
            'code'        => $code,
        ]);

        return array_filter([
            'channel'    => $channel,
            'sent_to'    => CarrierRecord::mask($destination, $channel === 'email'),
            'expires_in' => 15 * 60,
            'debug_code' => config('app.debug') ? $code : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Check the code. A correct one fully verifies the company.
     *
     * @throws ValidationException
     */
    public function confirmCode(Carrier $carrier, string $code): Carrier
    {
        $user = $carrier->user;

        $expired = $user->verification_code_expires_at === null
            || $user->verification_code_expires_at->isPast();

        if ($user->verification_code === null || $expired || ! hash_equals($user->verification_code, $code)) {
            throw ValidationException::withMessages([
                'code' => ['That code is wrong or has expired.'],
            ]);
        }

        // The record may have moved since sign-up, so re-check on confirm.
        $record = $this->lookup($carrier->dot_number, $carrier->mc_number);
        $this->applyRecord($carrier, $record);

        $user->forceFill([
            'verification_code'            => null,
            'verification_code_expires_at' => null,
            $user->verification_channel === 'phone' ? 'phone_verified_at' : 'email_verified_at' => now(),
        ])->save();

        $carrier->forceFill(['fmcsa_verified_at' => now()])->save();

        return $carrier->fresh();
    }

    /**
     * Periodic re-check: a lapsed authority closes the account.
     */
    public function recheck(Carrier $carrier): Carrier
    {
        try {
            $record = $this->lookup($carrier->dot_number, $carrier->mc_number);
            $this->applyRecord($carrier, $record);
        } catch (ValidationException $e) {
            $carrier->forceFill([
                'allowed_to_operate' => false,
                'fmcsa_checked_at'   => now(),
            ])->save();
        }

        return $carrier->fresh();
    }
}
