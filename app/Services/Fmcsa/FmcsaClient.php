<?php

namespace App\Services\Fmcsa;

interface FmcsaClient
{
    /**
     * USDOT raqami bo'yicha qidirish. Topilmasa null.
     */
    public function findByDotNumber(string $dotNumber): ?CarrierRecord;

    /**
     * MC (docket) raqami bo'yicha qidirish. Topilmasa null.
     */
    public function findByDocketNumber(string $docketNumber): ?CarrierRecord;
}
