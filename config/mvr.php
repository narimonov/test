<?php

/*
|--------------------------------------------------------------------------
| Motor vehicle records
|--------------------------------------------------------------------------
|
| SambaSafety is the default: it pulls from all 50 states and DC directly,
| normalises violation codes, and has a demo environment that exercises the
| whole order-and-collect flow without incurring state fees.
|
| Access to MVR data is not self-serve. A signed agreement is required, and
| every pull needs a permissible use under the DPPA plus written authorisation
| under the FCRA. This app refuses to order without a recorded consent — see
| MvrService::assertMayOrder().
|
| driver=fake => no external call, for local development and tests.
|
*/

return [

    'driver' => env('MVR_DRIVER', 'fake'),

    'providers' => [

        'sambasafety' => [
            'base_url'      => env('MVR_BASE_URL', 'https://api.sambasafety.io'),
            'client_id'     => env('MVR_CLIENT_ID'),
            'client_secret' => env('MVR_CLIENT_SECRET'),
            'account_id'    => env('MVR_ACCOUNT_ID'),
            'timeout'       => env('MVR_TIMEOUT', 30),
        ],

        'fake' => [],
    ],

    /*
    | A record pulled within this many days is shared with the next carrier
    | instead of being ordered again. States charge per pull, and a month-old
    | record is the same record.
    */
    'reuse_window_days' => env('MVR_REUSE_DAYS', 30),

    /*
    | Fallback per-state price, in cents, used until real rates are synced from
    | the provider. State fees vary widely; these are placeholders, and
    | MvrService reads mvr_state_rates first.
    */
    'default_cost_cents' => env('MVR_DEFAULT_COST', 1200),

    'fallback_rates' => [
        'CA' => 800, 'TX' => 900, 'IL' => 1200, 'NY' => 1000, 'FL' => 1100,
        'OH' => 950, 'PA' => 1300, 'IN' => 900, 'WI' => 1000, 'MI' => 1100,
        'MO' => 850, 'GA' => 1000, 'NC' => 1050, 'TN' => 900, 'AZ' => 950,
    ],
];
