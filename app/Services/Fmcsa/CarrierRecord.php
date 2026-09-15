<?php

namespace App\Services\Fmcsa;

/**
 * FMCSA'dan qaytgan kompaniya yozuvi — provayderdan qat'i nazar bir xil shakl.
 */
class CarrierRecord
{
    public $dotNumber;
    public $docketNumber;
    public $legalName;
    public $dbaName;
    public $statusCode;          // A = active, I = inactive
    public $allowedToOperate;    // bool
    public $phone;
    public $email;
    public $city;
    public $state;
    public $raw = [];

    public static function fromArray(array $data): self
    {
        $record = new self();

        $record->dotNumber        = $data['dotNumber'] ?? null;
        $record->docketNumber     = $data['docketNumber'] ?? null;
        $record->legalName        = $data['legalName'] ?? null;
        $record->dbaName          = $data['dbaName'] ?? null;
        $record->statusCode       = $data['statusCode'] ?? null;
        $record->allowedToOperate = filter_var($data['allowedToOperate'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $record->phone            = static::normalizePhone($data['phone'] ?? null);
        $record->email            = $data['email'] ?? null;
        $record->city             = $data['city'] ?? null;
        $record->state            = $data['state'] ?? null;
        $record->raw              = $data['raw'] ?? $data;

        return $record;
    }

    public function isOperational(): bool
    {
        return $this->allowedToOperate && strtoupper((string) $this->statusCode) !== 'I';
    }

    /** Kod yuborish uchun hech bo'lmasa bitta kontakt bo'lishi shart. */
    public function hasContact(): bool
    {
        return ! empty($this->phone) || ! empty($this->email);
    }

    public function displayName(): string
    {
        return $this->dbaName ?: ($this->legalName ?: 'Noma\'lum kompaniya');
    }

    public function toArray(): array
    {
        return [
            'dotNumber'        => $this->dotNumber,
            'docketNumber'     => $this->docketNumber,
            'legalName'        => $this->legalName,
            'dbaName'          => $this->dbaName,
            'statusCode'       => $this->statusCode,
            'allowedToOperate' => $this->allowedToOperate,
            'phone'            => $this->phone,
            'email'            => $this->email,
            'city'             => $this->city,
            'state'            => $this->state,
        ];
    }

    /** Kontaktni maskalash — foydalanuvchiga to'liq raqam ko'rsatilmaydi. */
    public static function mask(?string $value, bool $isEmail = false): ?string
    {
        if (! $value) {
            return null;
        }

        if ($isEmail) {
            [$name, $domain] = array_pad(explode('@', $value, 2), 2, '');

            return mb_substr($name, 0, 2) . str_repeat('*', max(1, mb_strlen($name) - 2)) . '@' . $domain;
        }

        return str_repeat('*', max(0, strlen($value) - 4)) . substr($value, -4);
    }

    protected static function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }

        if (strlen($digits) === 11 && $digits[0] === '1') {
            return '+' . $digits;
        }

        return $digits ? '+' . $digits : null;
    }
}
