<?php

/*
|--------------------------------------------------------------------------
| Outside reputation sources
|--------------------------------------------------------------------------
|
| Once a carrier's MC/DOT is verified we gather what is publicly said about it.
|
| Only sources that permit this are used. Google's Places API allows it under
| its terms. FMCSA publishes safety data openly. Indeed and Glassdoor have no
| public API and their terms forbid scraping, so they are not included —
| pulling them would put the platform at risk for data we cannot rely on.
|
*/

return [

    'enabled' => array_filter(explode(',', (string) env('REPUTATION_SOURCES', 'fmcsa_safety,google'))),

    'sources' => [

        'google' => [
            'label'    => 'Google',
            'api_key'  => env('GOOGLE_PLACES_KEY'),
            'base_url' => 'https://places.googleapis.com/v1',
        ],

        'fmcsa_safety' => [
            'label' => 'FMCSA safety record',
        ],
    ],

    // How long a snapshot is considered current before it is fetched again.
    'refresh_after_days' => env('REPUTATION_REFRESH_DAYS', 7),
];
