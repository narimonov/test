<?php

namespace App\Services\Reputation;

use App\Models\Carrier;

interface ReputationSource
{
    /**
     * What this source says about the carrier right now.
     *
     * @return array{
     *     source: string, source_label: string,
     *     rating: float|null, review_count: int|null,
     *     url: string|null, details: array
     * }|null  null when the source has nothing on this carrier
     */
    public function fetch(Carrier $carrier): ?array;

    public function key(): string;
}
