<?php

/*
|--------------------------------------------------------------------------
| FMCSA integratsiyasi
|--------------------------------------------------------------------------
|
| A company signing up is checked against FMCSA by MC or DOT number. The
| confirmation code goes to the phone or email FMCSA holds for that carrier,
| never to an address the user typed, so only someone who already controls
| that contact can open the account.
|
| driver=fake => no outbound request, for local development.
|
*/

return [

    'driver' => env('FMCSA_DRIVER', 'fake'),

    'qcmobile' => [
        'base_url'         => env('FMCSA_BASE_URL', 'https://mobile.fmcsa.dot.gov/qc/services'),
        'web_key'          => env('FMCSA_WEB_KEY'),
        'timeout'          => env('FMCSA_TIMEOUT', 15),

        // QCMobile carries no contact details; those come from Company Census.
        'census_base_url'  => env('FMCSA_CENSUS_BASE_URL', 'https://data.transportation.gov'),
        'census_dataset'   => env('FMCSA_CENSUS_DATASET'),
        'census_app_token' => env('FMCSA_CENSUS_APP_TOKEN'),
    ],

    /*
    | A carrier that is not active in FMCSA cannot sign up. Set false to
    | relax this in a test environment.
    */
    'require_active_status' => env('FMCSA_REQUIRE_ACTIVE', true),

    /*
    | How often to re-check a carrier, in days. A lapsed authority closes
    | the account.
    */
    'recheck_after_days' => env('FMCSA_RECHECK_DAYS', 30),
];
