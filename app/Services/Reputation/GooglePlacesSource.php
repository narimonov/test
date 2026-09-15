<?php

namespace App\Services\Reputation;

use App\Models\Carrier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google's rating for the company, found by name and location.
 *
 * Google is used rather than Indeed or Glassdoor because those have no public
 * API and their terms forbid scraping — collecting them would expose the
 * platform for data we could not stand behind anyway.
 */
class GooglePlacesSource implements ReputationSource
{
    /** @var array */
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function key(): string
    {
        return 'google';
    }

    public function fetch(Carrier $carrier): ?array
    {
        if (empty($this->config['api_key'])) {
            return null;
        }

        $query = trim(implode(' ', array_filter([
            $carrier->fmcsa_legal_name ?: $carrier->company_name,
            $carrier->city,
            $carrier->state,
        ])));

        $response = Http::timeout(15)
            ->withHeaders([
                'X-Goog-Api-Key'   => $this->config['api_key'],
                'X-Goog-FieldMask' => 'places.displayName,places.rating,places.userRatingCount,places.googleMapsUri',
            ])
            ->post(rtrim($this->config['base_url'], '/') . '/places:searchText', [
                'textQuery'  => $query,
                'maxResultCount' => 1,
            ]);

        if (! $response->successful()) {
            Log::warning('Google Places lookup failed', ['status' => $response->status()]);

            return null;
        }

        $place = $response->json('places.0');

        if (! $place || ! isset($place['rating'])) {
            return null;
        }

        return [
            'source'       => $this->key(),
            'source_label' => 'Google',
            'rating'       => round((float) $place['rating'], 2),
            'review_count' => (int) ($place['userRatingCount'] ?? 0),
            'url'          => $place['googleMapsUri'] ?? null,
            'details'      => ['matched_name' => $place['displayName']['text'] ?? null],
        ];
    }
}
