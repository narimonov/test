<?php

namespace App\Services;

use App\Models\Carrier;
use App\Services\Fmcsa\CarrierRecord;
use App\Services\Fmcsa\FmcsaClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Kompaniyani FMCSA bazasi bo'yicha tekshiradi va tasdiqlash kodini
 * FMCSA'da ro'yxatdan o'tgan kontaktga yuboradi.
 *
 * Asosiy g'oya: kod foydalanuvchi kiritgan emailga emas, FMCSA'dagi rasmiy
 * kontaktga ketadi — shuning uchun faqat kompaniyaning haqiqiy egasi
 * ro'yxatdan o'ta oladi.
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
     * MC yoki DOT bo'yicha qidiradi va natijani tekshiradi.
     *
     * @throws ValidationException topilmasa yoki faol bo'lmasa
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
                'dot_number' => ['Bu MC/DOT raqam FMCSA bazasida topilmadi. Raqamni tekshiring.'],
            ]);
        }

        if (config('fmcsa.require_active_status') && ! $record->isOperational()) {
            throw ValidationException::withMessages([
                'dot_number' => ['Bu kompaniya FMCSA bo\'yicha faol emas (authority to\'xtatilgan yoki out of service).'],
            ]);
        }

        return $record;
    }

    /**
     * Allaqachon ro'yxatdan o'tgan DOT raqamni ikkinchi marta ochib bo'lmaydi.
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
                'dot_number' => ['Bu DOT raqam bilan akkaunt allaqachon mavjud. Kompaniyangiz admini bilan bog\'laning.'],
            ]);
        }
    }

    /** FMCSA yozuvini carrier modeliga yozadi. */
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
     * Kod yuborish uchun mavjud kanallar — maskalangan holda.
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
     * Tasdiqlash kodini FMCSA kontaktiga yuboradi.
     *
     * SMS/email gateway hali ulanmagan — kod log'ga yoziladi va APP_DEBUG
     * yoqilganda javobda qaytariladi.
     */
    public function issueCode(Carrier $carrier, string $channel): array
    {
        $destination = $channel === 'phone' ? $carrier->fmcsa_phone : $carrier->fmcsa_email;

        if (! $destination) {
            throw ValidationException::withMessages([
                'channel' => ['FMCSA bazasida bu kanal uchun kontakt yo\'q.'],
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
     * Kodni tekshiradi. To'g'ri bo'lsa kompaniya to'liq tasdiqlanadi.
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
                'code' => ['Kod noto\'g\'ri yoki muddati tugagan.'],
            ]);
        }

        // Kod eskirgan bo'lishi mumkin — tasdiqlash paytida holat qayta tekshiriladi.
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
     * Davriy qayta tekshiruv: authority to'xtatilgan bo'lsa akkaunt yopiladi.
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
