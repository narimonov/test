<?php

namespace App\Services\Reputation;

use App\Models\Carrier;

/** Local development, so the reputation panel has something to render. */
class FakeReputationSource implements ReputationSource
{
    public function key(): string
    {
        return 'google';
    }

    public function fetch(Carrier $carrier): ?array
    {
        // Deterministic from the DOT number so it does not jump between loads.
        $seed = (int) substr((string) ($carrier->dot_number ?: $carrier->id), -2);

        return [
            'source'       => $this->key(),
            'source_label' => 'Google',
            'rating'       => round(3 + ($seed % 20) / 10, 2),
            'review_count' => 12 + ($seed % 90),
            'url'          => null,
            'details'      => ['note' => 'Development source — not a real rating.'],
        ];
    }
}
