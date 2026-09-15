<?php

/*
|--------------------------------------------------------------------------
| Driver scoring criteria
|--------------------------------------------------------------------------
|
| Change the criteria here, not in code.
|
| Two kinds of rule:
|
|   1) knockouts — an outright no. Fail one and the driver is disqualified
|                  and never scored.
|   2) criteria  — weighted scoring. Each carries a weight and the result is
|                  a weighted average between 0 and 100.
|
| Knockout operators:
|   gte, lte, gt, lt, eq, neq, in, not_in, is_true, is_false,
|   date_after_today (the date must be in the future)
|
| Criteria types:
|   bands   — steps over a number. Each band is ['min'=>,'max'=>,'points'=>]
|             (min/max optional; the first matching band wins)
|   map     — value => points ('_default' as the fallback)
|   boolean — true_points / false_points
|   set     — a json array (endorsements, equipment). Points per match, capped
|             by 'max_points'.
|
| A null value scores 'null_points' (0 unless set).
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | 1. Knockouts — an outright no
    |----------------------------------------------------------------------
    | A job post can override these for itself, e.g. when one job is happy
    | with a year of experience.
    */
    'knockouts' => [
        [
            'key'      => 'cdl_class',
            'operator' => 'in',
            'value'    => ['A'],
            'reason'   => 'Not a Class A CDL',
        ],
        [
            'key'      => 'years_experience',
            'operator' => 'gte',
            'value'    => 1,
            'reason'   => 'Less than a year of experience',
        ],
        [
            'key'      => 'can_pass_drug_test',
            'operator' => 'is_true',
            'reason'   => 'Cannot pass a drug test',
        ],
        [
            'key'      => 'license_suspended_ever',
            'operator' => 'is_false',
            'reason'   => 'Licence has been suspended',
        ],
        [
            'key'      => 'sap_status',
            'operator' => 'not_in',
            'value'    => ['in_program'],
            'reason'   => 'Has not completed the SAP program',
        ],
        [
            'key'      => 'cdl_expires_at',
            'operator' => 'date_after_today',
            'reason'   => 'CDL has expired',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | 2. Weighted criteria
    |----------------------------------------------------------------------
    | Weights are relative; they do not have to add up to 100 — the service
    | normalises them.
    */
    'criteria' => [

        [
            'key'    => 'years_experience',
            'label'  => 'Experience (years)',
            'group'  => 'experience',
            'type'   => 'bands',
            'weight' => 25,
            'bands'  => [
                ['min' => 5,   'points' => 100, 'label' => '5+ years'],
                ['min' => 3,   'points' => 85,  'label' => '3-5 years'],
                ['min' => 2,   'points' => 70,  'label' => '2-3 years'],
                ['min' => 1,   'points' => 45,  'label' => '1-2 years'],
                ['min' => 0,   'points' => 0,   'label' => 'Under a year'],
            ],
        ],

        [
            'key'    => 'accidents_3y',
            'label'  => 'Accidents in the last 3 years',
            'group'  => 'safety',
            'type'   => 'bands',
            'weight' => 20,
            'bands'  => [
                ['max' => 0, 'points' => 100, 'label' => 'None'],
                ['max' => 1, 'points' => 55,  'label' => 'One'],
                ['max' => 2, 'points' => 20,  'label' => 'Two'],
                ['points' => 0, 'label' => 'Three or more'],
            ],
        ],

        [
            'key'    => 'moving_violations_3y',
            'label'  => 'Moving violations in the last 3 years',
            'group'  => 'safety',
            'type'   => 'bands',
            'weight' => 12,
            'bands'  => [
                ['max' => 0, 'points' => 100, 'label' => 'None'],
                ['max' => 1, 'points' => 75,  'label' => 'One'],
                ['max' => 2, 'points' => 45,  'label' => 'Two'],
                ['max' => 3, 'points' => 15,  'label' => 'Three'],
                ['points' => 0, 'label' => 'Four or more'],
            ],
        ],

        [
            'key'    => 'jobs_last_3_years',
            'label'  => 'Job hopping (jobs in 3 years)',
            'group'  => 'stability',
            'type'   => 'bands',
            'weight' => 15,
            'bands'  => [
                ['max' => 1, 'points' => 100, 'label' => 'One'],
                ['max' => 2, 'points' => 85,  'label' => 'Two'],
                ['max' => 3, 'points' => 60,  'label' => 'Three'],
                ['max' => 4, 'points' => 30,  'label' => 'Four'],
                ['points' => 0, 'label' => 'Five or more'],
            ],
        ],

        [
            'key'    => 'longest_tenure_months',
            'label'  => 'Longest tenure (months)',
            'group'  => 'stability',
            'type'   => 'bands',
            'weight' => 8,
            'bands'  => [
                ['min' => 24, 'points' => 100, 'label' => '2+ years'],
                ['min' => 12, 'points' => 75,  'label' => '1-2 years'],
                ['min' => 6,  'points' => 45,  'label' => '6-12 months'],
                ['points' => 15, 'label' => 'Under 6 months'],
            ],
        ],

        [
            'key'    => 'unemployment_gap_months',
            'label'  => 'Unemployment gap (months)',
            'group'  => 'stability',
            'type'   => 'bands',
            'weight' => 6,
            'bands'  => [
                ['max' => 1, 'points' => 100, 'label' => 'None'],
                ['max' => 3, 'points' => 70,  'label' => '1-3 months'],
                ['max' => 6, 'points' => 40,  'label' => '3-6 months'],
                ['points' => 10, 'label' => '6+ months'],
            ],
        ],

        [
            'key'              => 'endorsements',
            'label'            => 'Endorsements',
            'group'            => 'qualification',
            'type'             => 'set',
            'weight'           => 8,
            'valuable'         => ['hazmat' => 40, 'tanker' => 30, 'doubles' => 20, 'twic' => 10],
            'max_points'       => 100,
        ],

        [
            'key'        => 'equipment_experience',
            'label'      => 'Equipment experience',
            'group'      => 'qualification',
            'type'       => 'set',
            'weight'     => 6,
            'valuable'   => ['dry_van' => 25, 'reefer' => 30, 'flatbed' => 25, 'tanker' => 20],
            'max_points' => 100,
        ],

        [
            'key'    => 'sap_status',
            'label'  => 'SAP status',
            'group'  => 'safety',
            'type'   => 'map',
            'weight' => 5,
            'map'    => [
                'none'      => 100,
                'completed' => 40,
                'in_program' => 0,
                '_default'  => 100,
            ],
        ],

        [
            'key'          => 'dui_ever',
            'label'        => 'Has a DUI',
            'group'        => 'safety',
            'type'         => 'boolean',
            'weight'       => 10,
            'true_points'  => 0,
            'false_points' => 100,
        ],

        [
            'key'    => 'work_authorization',
            'label'  => 'Work authorisation',
            'group'  => 'eligibility',
            'type'   => 'map',
            'weight' => 5,
            'map'    => [
                'us_citizen' => 100,
                'green_card' => 95,
                'ead'        => 70,
                'other'      => 30,
                '_default'   => 50,
            ],
        ],

    ],

    /*
    |----------------------------------------------------------------------
    | 3. Grades
    |----------------------------------------------------------------------
    */
    'tiers' => [
        'A' => 85,   // 85+  call these first
        'B' => 70,   // 70-84
        'C' => 50,   // 50-69
        'D' => 0,    // below 50
    ],
];
