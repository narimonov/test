<?php

namespace App\Services\Fmcsa;

/**
 * Local development and tests. Used when FMCSA_DRIVER=fake; makes no
 * outbound request.
 *
 * Convention: a DOT number starting with 9 is inactive, one starting with 0
 * is not found, anything else is active.
 */
class FakeFmcsaClient implements FmcsaClient
{
    /** @var array<string, CarrierRecord> */
    protected $overrides = [];

    public function stub(string $number, ?CarrierRecord $record): void
    {
        $this->overrides[$number] = $record;
    }

    public function findByDotNumber(string $dotNumber): ?CarrierRecord
    {
        return $this->resolve($dotNumber, 'dot');
    }

    public function findByDocketNumber(string $docketNumber): ?CarrierRecord
    {
        return $this->resolve($docketNumber, 'mc');
    }

    protected function resolve(string $number, string $kind): ?CarrierRecord
    {
        $number = preg_replace('/\D+/', '', $number);

        if (array_key_exists($number, $this->overrides)) {
            return $this->overrides[$number];
        }

        if ($number === '' || str_starts_with($number, '0')) {
            return null;
        }

        $inactive = str_starts_with($number, '9');

        // Deterministic safety figures, so the reputation panel has something
        // realistic to show without hitting FMCSA.
        $seed = (int) substr($number, -2);

        return CarrierRecord::fromArray([
            'dotNumber'        => $kind === 'dot' ? $number : '1' . $number,
            'docketNumber'     => $kind === 'mc' ? $number : null,
            'legalName'        => 'TEST CARRIER ' . $number . ' LLC',
            'dbaName'          => null,
            'statusCode'       => $inactive ? 'I' : 'A',
            'allowedToOperate' => ! $inactive,
            'phone'            => '+1555' . str_pad(substr($number, -7), 7, '0', STR_PAD_LEFT),
            'email'            => 'dispatch+' . $number . '@fmcsa-test.example',
            'city'             => 'Chicago',
            'state'            => 'IL',
            'raw'              => [
                'dotNumber'      => $number,
                'safetyRating'   => $inactive ? 'Conditional' : 'Satisfactory',
                'driverOosRate'  => round(2 + ($seed % 7) * 0.5, 2),
                'vehicleOosRate' => round(12 + ($seed % 15), 2),
                'totalDrivers'   => 20 + ($seed % 80),
                'totalPowerUnits' => 15 + ($seed % 60),
            ],
        ]);
    }
}
