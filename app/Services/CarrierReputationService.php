<?php

namespace App\Services;

use App\Models\Carrier;
use App\Models\CarrierReputationSnapshot;
use App\Models\Review;
use App\Services\Reputation\ReputationSource;
use Illuminate\Support\Facades\Log;

/**
 * Gathers what is publicly said about a carrier once its MC/DOT is known, and
 * puts it next to the reviews left on this platform.
 *
 * Snapshots are dated rather than overwritten, so a rating that moves shows up
 * as a change instead of quietly replacing the previous number.
 */
class CarrierReputationService
{
    /** @var ReputationSource[] */
    protected $sources;

    /** @var ReputationService */
    protected $platform;

    public function __construct(array $sources, ReputationService $platform)
    {
        $this->sources = $sources;
        $this->platform = $platform;
    }

    /** Fetch anything stale and return the current picture. */
    public function refresh(Carrier $carrier, bool $force = false): array
    {
        foreach ($this->sources as $source) {
            if (! $force && $this->isFresh($carrier, $source->key())) {
                continue;
            }

            try {
                $data = $source->fetch($carrier);
            } catch (\Throwable $e) {
                // One source being down must not take the whole panel with it.
                Log::warning('Reputation source failed', ['source' => $source->key(), 'message' => $e->getMessage()]);

                continue;
            }

            if (! $data) {
                continue;
            }

            CarrierReputationSnapshot::create($data + [
                'carrier_id' => $carrier->id,
                'fetched_at' => now(),
            ]);
        }

        return $this->summary($carrier);
    }

    public function summary(Carrier $carrier): array
    {
        $latest = CarrierReputationSnapshot::where('carrier_id', $carrier->id)
            ->orderByDesc('fetched_at')
            ->get()
            ->unique('source')
            ->values();

        $platform = $this->platform->stats($carrier);

        $rated = $latest->whereNotNull('rating');

        return [
            'sources'  => $latest,
            'platform' => [
                'source'       => 'platform',
                'source_label' => 'DriverHub reviews',
                'rating'       => $platform['average'],
                'review_count' => $platform['total'],
                'details'      => ['breakdown' => $platform['breakdown']],
            ],
            // Outside ratings and our own, averaged; null when nobody has rated.
            'overall' => $this->overall($rated->pluck('rating')->all(), $platform['average'], $platform['total']),
        ];
    }

    protected function overall(array $externalRatings, $platformRating, int $platformCount): ?float
    {
        $ratings = array_map('floatval', $externalRatings);

        if ($platformRating !== null && $platformCount > 0) {
            $ratings[] = (float) $platformRating;
        }

        return $ratings ? round(array_sum($ratings) / count($ratings), 2) : null;
    }

    protected function isFresh(Carrier $carrier, string $source): bool
    {
        $days = (int) config('reputation_sources.refresh_after_days', 7);

        return CarrierReputationSnapshot::where('carrier_id', $carrier->id)
            ->where('source', $source)
            ->where('fetched_at', '>=', now()->subDays($days))
            ->exists();
    }
}
