<?php

namespace App\Services\Reputation;

use App\Models\Carrier;
use App\Services\Fmcsa\FmcsaClient;

/**
 * FMCSA's own safety record, which is public and authoritative — worth more to
 * a driver than a star rating.
 *
 * The out-of-service percentages are turned into a 0-5 figure so it can sit
 * alongside other sources, but the underlying numbers are kept in details
 * because they are what actually matters.
 */
class FmcsaSafetySource implements ReputationSource
{
    /** @var FmcsaClient */
    protected $fmcsa;

    public function __construct(FmcsaClient $fmcsa)
    {
        $this->fmcsa = $fmcsa;
    }

    public function key(): string
    {
        return 'fmcsa_safety';
    }

    public function fetch(Carrier $carrier): ?array
    {
        if (! $carrier->dot_number) {
            return null;
        }

        $record = $this->fmcsa->findByDotNumber($carrier->dot_number);

        if (! $record) {
            return null;
        }

        $raw = $record->raw ?? [];

        $driverOos = $this->percentage($raw['driverOosRate'] ?? null);
        $vehicleOos = $this->percentage($raw['vehicleOosRate'] ?? null);

        return [
            'source'       => $this->key(),
            'source_label' => 'FMCSA safety record',
            'rating'       => $this->toStars($driverOos, $vehicleOos),
            'review_count' => null,
            'url'          => 'https://safer.fmcsa.dot.gov/query.asp?searchtype=ANY&query_type=queryCarrierSnapshot'
                . '&query_param=USDOT&query_string=' . $carrier->dot_number,
            'details'      => array_filter([
                'allowed_to_operate' => $record->allowedToOperate,
                'safety_rating'      => $raw['safetyRating'] ?? null,
                'driver_oos_rate'    => $driverOos,
                'vehicle_oos_rate'   => $vehicleOos,
                'total_drivers'      => $raw['totalDrivers'] ?? null,
                'total_power_units'  => $raw['totalPowerUnits'] ?? null,
            ], fn ($value) => $value !== null),
        ];
    }

    protected function percentage($value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * Higher out-of-service rates mean a worse record. The national averages
     * sit near 5% for drivers and 20% for vehicles, so those anchor the scale.
     */
    protected function toStars(?float $driverOos, ?float $vehicleOos): ?float
    {
        if ($driverOos === null && $vehicleOos === null) {
            return null;
        }

        $driverScore = $driverOos === null ? null : max(0, 1 - ($driverOos / 10));
        $vehicleScore = $vehicleOos === null ? null : max(0, 1 - ($vehicleOos / 40));

        $scores = array_filter([$driverScore, $vehicleScore], fn ($value) => $value !== null);

        return round(array_sum($scores) / count($scores) * 5, 2);
    }
}
