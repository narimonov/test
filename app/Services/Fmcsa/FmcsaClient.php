<?php

namespace App\Services\Fmcsa;

interface FmcsaClient
{
    /**
     * Find by USDOT number. Null when there is no such carrier.
     */
    public function findByDotNumber(string $dotNumber): ?CarrierRecord;

    /**
     * Find by MC docket number. Null when there is no such carrier.
     */
    public function findByDocketNumber(string $docketNumber): ?CarrierRecord;
}
