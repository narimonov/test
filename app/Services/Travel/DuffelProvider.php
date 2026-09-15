<?php

namespace App\Services\Travel;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Duffel.
 *
 * Chosen over Expedia because Expedia's Rapid API distributes lodging and does
 * not sell flights. Duffel has self-serve access and a test mode, so the whole
 * search-and-book path can be exercised before any commercial agreement.
 */
class DuffelProvider implements FlightProvider
{
    /** @var array */
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function name(): string
    {
        return 'duffel';
    }

    public function search(string $origin, string $destination, string $departOn, array $passenger = []): array
    {
        $response = $this->request()->post($this->url('air/offer_requests'), [
            'data' => [
                'slices'      => [[
                    'origin'         => strtoupper($origin),
                    'destination'    => strtoupper($destination),
                    'departure_date' => $departOn,
                ]],
                'passengers'  => [['type' => 'adult']],
                'cabin_class' => config('travel.cabin_class', 'economy'),
            ],
        ]);

        if (! $response->successful()) {
            Log::warning('Flight search failed', ['status' => $response->status()]);

            throw new RuntimeException('Could not search flights right now.');
        }

        return array_map(
            fn (array $offer) => $this->toOffer($offer),
            $response->json('data.offers', [])
        );
    }

    public function book(string $offerId, array $passenger): array
    {
        $response = $this->request()->post($this->url('air/orders'), [
            'data' => [
                'type'             => 'instant',
                'selected_offers'  => [$offerId],
                'passengers'       => [[
                    'id'          => $passenger['id'] ?? null,
                    'given_name'  => $passenger['first_name'] ?? '',
                    'family_name' => $passenger['last_name'] ?? '',
                    'born_on'     => $passenger['date_of_birth'] ?? null,
                    'email'       => $passenger['email'] ?? null,
                    'phone_number' => $passenger['phone'] ?? null,
                ]],
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('The airline rejected the booking: ' . $response->body());
        }

        return [
            'reference'         => $response->json('data.id'),
            'booking_reference' => $response->json('data.booking_reference'),
            'status'            => 'booked',
            'payload'           => $response->json('data', []),
        ];
    }

    // ------------------------------------------------------------------

    protected function request()
    {
        if (empty($this->config['token'])) {
            throw new RuntimeException('Flight provider credentials are not configured.');
        }

        return Http::withToken($this->config['token'])
            ->withHeaders(['Duffel-Version' => $this->config['api_version'] ?? 'v2'])
            ->timeout($this->config['timeout'] ?? 30)
            ->acceptJson();
    }

    protected function url(string $path): string
    {
        return rtrim($this->config['base_url'], '/') . '/' . $path;
    }

    protected function toOffer(array $offer): FlightOffer
    {
        $slice = $offer['slices'][0] ?? [];
        $segments = $slice['segments'] ?? [];
        $first = $segments[0] ?? [];
        $last = end($segments) ?: $first;

        return FlightOffer::fromArray([
            'id'           => $offer['id'] ?? null,
            'origin'       => $slice['origin']['iata_code'] ?? null,
            'destination'  => $slice['destination']['iata_code'] ?? null,
            'departsAt'    => $first['departing_at'] ?? null,
            'arrivesAt'    => $last['arriving_at'] ?? null,
            'airline'      => $first['marketing_carrier']['name'] ?? null,
            'flightNumber' => ($first['marketing_carrier']['iata_code'] ?? '') . ($first['marketing_carrier_flight_number'] ?? ''),
            'amountCents'  => isset($offer['total_amount']) ? (int) round(((float) $offer['total_amount']) * 100) : null,
            'currency'     => $offer['total_currency'] ?? 'USD',
            'stops'        => max(0, count($segments) - 1),
            'expiresAt'    => $offer['expires_at'] ?? null,
            'raw'          => $offer,
        ]);
    }
}
