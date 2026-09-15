<?php

/*
|--------------------------------------------------------------------------
| Carrier subscription plans
|--------------------------------------------------------------------------
|
| Three tiers. What separates them is how much of the driver market a carrier
| can reach and how much of the work we do for them:
|
|   Starter ($50)  — post a few jobs and work the applicants who come to you.
|   Growth  ($100) — go find drivers yourself: the whole pool, your own
|                    scoring weights, direct chat, manual entry.
|   Pro     ($500) — we do the finding. A recruiter works your req, sends
|                    matched drivers and helps them through onboarding.
|
| The price gap between Growth and Pro is large because Pro is people's time,
| not software.
|
*/

return [

    'currency' => 'USD',

    'tiers' => [

        'starter' => [
            'name'         => 'Starter',
            'price_cents'  => 5000,
            'tagline'      => 'Post jobs and work your applicants.',
            'max_active_jobs'     => 3,
            'talent_pool_limit'   => 25,   // most results a search will return
            'features' => [
                'applicant_scoring'   => true,
                'talent_pool'         => true,
                'chat_with_applicants' => true,
                'chat_with_any_driver' => false,
                'criteria_tuning'     => false,
                'manual_driver_entry' => false,
                'personal_recruiting' => false,
                'priority_support'    => false,
            ],
            'highlights' => [
                'Up to 3 active job posts',
                'Applicants ranked by your criteria',
                'Talent pool search (top 25 results)',
                'Chat with drivers who applied to you',
            ],
        ],

        'growth' => [
            'name'         => 'Growth',
            'price_cents'  => 10000,
            'tagline'      => 'Go find the drivers yourself.',
            'max_active_jobs'   => null,   // unlimited
            'talent_pool_limit' => null,
            'features' => [
                'applicant_scoring'    => true,
                'talent_pool'          => true,
                'chat_with_applicants' => true,
                'chat_with_any_driver' => true,
                'criteria_tuning'      => true,
                'manual_driver_entry'  => true,
                'personal_recruiting'  => false,
                'priority_support'     => false,
            ],
            'highlights' => [
                'Unlimited job posts',
                'Full talent pool, no result cap',
                'Tune the scoring weights to your fleet',
                'Chat with any driver, not just applicants',
                'Add drivers you sourced yourself',
            ],
        ],

        'pro' => [
            'name'         => 'Pro',
            'price_cents'  => 50000,
            'tagline'      => 'We recruit for you.',
            'max_active_jobs'   => null,
            'talent_pool_limit' => null,
            'features' => [
                'applicant_scoring'    => true,
                'talent_pool'          => true,
                'chat_with_applicants' => true,
                'chat_with_any_driver' => true,
                'criteria_tuning'      => true,
                'manual_driver_entry'  => true,
                'personal_recruiting'  => true,
                'priority_support'     => true,
            ],
            'highlights' => [
                'Everything in Growth',
                'A recruiter works your req and sends matched drivers',
                'We help each driver through onboarding',
                'Priority support with a human agent',
            ],
        ],
    ],
];
