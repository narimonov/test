<?php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;

/**
 * Claimed experience cannot exceed the time since the CDL was issued.
 *
 * A licence issued in 2023 cannot support ten years of experience in 2026.
 * A small tolerance is allowed, because drivers round to whole years.
 */
class ExperienceMatchesCdlIssueDate implements Rule
{
    /** @var string|null */
    protected $issuedAt;

    /** @var float How much overshoot is tolerated, in years. */
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
            return true;   // a separate rule validates the date format
        }

        if ($issued->isFuture()) {
            return false;
        }

        $this->allowed = round($issued->floatDiffInYears(now()), 1);

        return (float) $value <= $this->allowed + $this->toleranceYears;
    }

    public function message()
    {
        return 'The experience given does not match the CDL issue date — '
            . "that date supports at most {$this->allowed} years.";
    }
}
