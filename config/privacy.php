<?php

/*
|--------------------------------------------------------------------------
| Privacy notice
|--------------------------------------------------------------------------
|
| Bump the version whenever the policy text changes in a way users must
| re-accept. Acceptance is stored per user against the version they saw.
|
*/

return [
    'version'        => env('PRIVACY_VERSION', '2026-09-15'),
    'effective_date' => '2026-09-15',
    'contact_email'  => env('PRIVACY_CONTACT', 'privacy@driverhub.example'),
];
