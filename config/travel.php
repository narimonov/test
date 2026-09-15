<?php

/*
|--------------------------------------------------------------------------
| Driver travel
|--------------------------------------------------------------------------
|
| Duffel, not Expedia. Expedia's Rapid API distributes lodging — it does not
| sell flights — so it cannot do what this needs. Duffel is a flight API with
| self-serve access and a test mode, which makes it the practical choice;
| Amadeus Self-Service is the alternative if volume pricing matters later.
|
| A carrier that books elsewhere can record that booking instead, so
| onboarding still knows travel is handled.
|
*/

return [

    'driver' => env('TRAVEL_DRIVER', 'fake'),

    'providers' => [
        'duffel' => [
            'base_url'    => env('DUFFEL_BASE_URL', 'https://api.duffel.com'),
            'token'       => env('DUFFEL_TOKEN'),
            'api_version' => env('DUFFEL_VERSION', 'v2'),
            'timeout'     => env('DUFFEL_TIMEOUT', 30),
        ],

        'fake' => [],
    ],

    // Offers expire; anything older than this is re-searched rather than booked.
    'offer_ttl_minutes' => env('TRAVEL_OFFER_TTL', 20),

    'cabin_class' => env('TRAVEL_CABIN', 'economy'),
];
