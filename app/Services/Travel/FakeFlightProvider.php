<?php

namespace App\Services\Travel;

/**
 * Local development. Returns a small, deterministic set of offers so the
 * search-select-book flow can be worked without a provider account.
 */
class FakeFlightProvider implements FlightProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function search(string $origin, string $destination, string $departOn, array $passenger = []): array
    {
        $origin = strtoupper($origin);
        $destination = strtoupper($destination);

        $templates = [
            ['airline' => 'Midwest Air', 'flight' => 'MW412', 'hour' => 7,  'stops' => 0, 'cents' => 18400],
            ['airline' => 'Great Lakes', 'flight' => 'GL908', 'hour' => 13, 'stops' => 1, 'cents' => 14200],
            ['airline' => 'Prairie Jet',  'flight' => 'PJ221', 'hour' => 18, 'stops' => 0, 'cents' => 22750],
        ];

        return array_map(function (array $template, int $index) use ($origin, $destination, $departOn) {
            $departs = \Carbon\Carbon::parse($departOn)->setTime($template['hour'], 0);

            return FlightOffer::fromArray([
                'id'           => "fake-offer-{$index}-{$origin}{$destination}",
                'origin'       => $origin,
                'destination'  => $destination,
                'departsAt'    => $departs->toIso8601String(),
                'arrivesAt'    => $departs->copy()->addHours(2 + $template['stops'] * 2)->toIso8601String(),
                'airline'      => $template['airline'],
                'flightNumber' => $template['flight'],
                'amountCents'  => $template['cents'],
                'currency'     => 'USD',
                'stops'        => $template['stops'],
                'expiresAt'    => now()->addMinutes(config('travel.offer_ttl_minutes', 20))->toIso8601String(),
                'raw'          => ['note' => 'Development provider — nothing was booked.'],
            ]);
        }, $templates, array_keys($templates));
    }

    public function book(string $offerId, array $passenger): array
    {
        return [
            'reference'         => 'fake-order-' . substr(md5($offerId), 0, 8),
            'booking_reference' => strtoupper(substr(md5($offerId), 0, 6)),
            'status'            => 'booked',
            'payload'           => ['offer_id' => $offerId, 'passenger' => $passenger],
        ];
    }
}
