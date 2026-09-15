<?php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;

/**
 * Driver ko'rsatgan tajriba CDL olingan sanadan ko'p bo'lishi mumkin emas.
 *
 * Misol: CDL 2023-yilda olingan bo'lsa, 2026-yilda 10 yillik tajriba
 * ko'rsatib bo'lmaydi. Kichik farqga yo'l qo'yiladi (tolerance), chunki
 * driver oylarni yaxlitlab yozishi mumkin.
 */
class ExperienceMatchesCdlIssueDate implements Rule
{
    /** @var string|null */
    protected $issuedAt;

    /** @var float Necha yilgacha farqqa yo'l qo'yiladi. */
    protected $toleranceYears;

    /** @var float */
    protected $allowed = 0.0;

    public function __construct(?string $issuedAt, float $toleranceYears = 0.5)
    {
        $this->issuedAt = $issuedAt;
        $this->toleranceYears = $toleranceYears;
    }

    public function passes($attribute, $value)
    {
        if (! $this->issuedAt || $value === null || $value === '') {
            return true;
        }

        try {
            $issued = Carbon::parse($this->issuedAt);
        } catch (\Exception $e) {
            return true;   // sana formatini alohida qoida tekshiradi
        }

        if ($issued->isFuture()) {
            return false;
        }

        $this->allowed = round($issued->floatDiffInYears(now()), 1);

        return (float) $value <= $this->allowed + $this->toleranceYears;
    }

    public function message()
    {
        return "Ko'rsatilgan tajriba CDL olingan sanaga mos kelmaydi — "
            . "bu sana bo'yicha eng ko'pi {$this->allowed} yil bo'lishi mumkin.";
    }
}
