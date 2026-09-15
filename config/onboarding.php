<?php

/*
|--------------------------------------------------------------------------
| Driver onboarding
|--------------------------------------------------------------------------
|
| The steps a hired driver goes through before the first dispatch. This is the
| federal baseline for a company driver, in the order carriers normally work
| it; a carrier can skip an optional step but not a required one.
|
| Each driver gets a copy of this list when they are hired, so editing the
| template later does not rewrite anyone's record.
|
| owner: who is expected to act — carrier, driver, or platform.
|
*/

return [

    'tracks' => [

        'company_driver' => [
            'label' => 'Company driver',
            'steps' => [
                [
                    'key' => 'application', 'label' => 'Employment application on file',
                    'stage' => 'screening', 'owner' => 'driver', 'required' => true,
                    'description' => 'Full 3-year employment and 10-year CDL history (49 CFR 391.21).',
                ],
                [
                    'key' => 'cdl_verification', 'label' => 'CDL and medical card verified',
                    'stage' => 'screening', 'owner' => 'carrier', 'required' => true,
                    'description' => 'Class, endorsements and expiry checked against the uploaded documents.',
                ],
                [
                    'key' => 'mvr', 'label' => 'Motor vehicle record reviewed',
                    'stage' => 'screening', 'owner' => 'carrier', 'required' => true,
                    'description' => 'MVR from every state the driver was licensed in over the past 3 years.',
                ],
                [
                    'key' => 'psp', 'label' => 'PSP report reviewed',
                    'stage' => 'screening', 'owner' => 'carrier', 'required' => false,
                    'description' => 'FMCSA Pre-Employment Screening Program crash and inspection history.',
                ],
                [
                    'key' => 'previous_employers', 'label' => 'Previous employer checks sent',
                    'stage' => 'screening', 'owner' => 'carrier', 'required' => true,
                    'description' => 'Safety performance history requests to employers for the past 3 years.',
                ],
                [
                    'key' => 'clearinghouse', 'label' => 'Drug & Alcohol Clearinghouse query',
                    'stage' => 'compliance', 'owner' => 'carrier', 'required' => true,
                    'description' => 'Full query before the driver performs any safety-sensitive function.',
                ],
                [
                    'key' => 'drug_test', 'label' => 'Pre-employment drug test passed',
                    'stage' => 'compliance', 'owner' => 'driver', 'required' => true,
                    'description' => 'DOT panel, negative result received before dispatch.',
                ],
                [
                    'key' => 'dot_physical', 'label' => 'DOT physical current',
                    'stage' => 'compliance', 'owner' => 'driver', 'required' => true,
                    'description' => 'Medical examiner certificate valid and on file.',
                ],
                [
                    'key' => 'dqf', 'label' => 'Driver qualification file assembled',
                    'stage' => 'compliance', 'owner' => 'carrier', 'required' => true,
                    'description' => 'Everything required by 49 CFR Part 391 in one file.',
                ],
                [
                    'key' => 'travel', 'label' => 'Travel to orientation arranged',
                    'stage' => 'training', 'owner' => 'carrier', 'required' => false,
                    'description' => 'Flight or other transport booked, or confirmed not needed.',
                ],
                [
                    'key' => 'orientation', 'label' => 'Orientation completed',
                    'stage' => 'training', 'owner' => 'carrier', 'required' => true,
                    'description' => 'Company policy, safety and hours-of-service training.',
                ],
                [
                    'key' => 'road_test', 'label' => 'Road test passed',
                    'stage' => 'training', 'owner' => 'carrier', 'required' => true,
                    'description' => 'Road test and certificate, or an accepted equivalent (49 CFR 391.31).',
                ],
                [
                    'key' => 'eld_training', 'label' => 'ELD training done',
                    'stage' => 'training', 'owner' => 'carrier', 'required' => true,
                    'description' => 'Driver can operate the logging device and knows the edit rules.',
                ],
                [
                    'key' => 'equipment', 'label' => 'Truck assigned',
                    'stage' => 'equipment', 'owner' => 'carrier', 'required' => true,
                    'description' => 'Unit number, keys, fuel card and pre-trip walkthrough.',
                ],
                [
                    'key' => 'payroll', 'label' => 'Payroll and direct deposit set up',
                    'stage' => 'equipment', 'owner' => 'driver', 'required' => true,
                    'description' => 'W-4, I-9 and bank details collected.',
                ],
                [
                    'key' => 'first_dispatch', 'label' => 'First dispatch',
                    'stage' => 'equipment', 'owner' => 'carrier', 'required' => true,
                    'description' => 'Driver is loaded and rolling.',
                ],
            ],
        ],

        'owner_operator' => [
            'label' => 'Owner operator',
            'steps' => [
                ['key' => 'application', 'label' => 'Application on file', 'stage' => 'screening', 'owner' => 'driver', 'required' => true],
                ['key' => 'cdl_verification', 'label' => 'CDL and medical card verified', 'stage' => 'screening', 'owner' => 'carrier', 'required' => true],
                ['key' => 'mvr', 'label' => 'Motor vehicle record reviewed', 'stage' => 'screening', 'owner' => 'carrier', 'required' => true],
                ['key' => 'clearinghouse', 'label' => 'Clearinghouse query', 'stage' => 'compliance', 'owner' => 'carrier', 'required' => true],
                ['key' => 'drug_test', 'label' => 'Pre-employment drug test passed', 'stage' => 'compliance', 'owner' => 'driver', 'required' => true],
                ['key' => 'dot_physical', 'label' => 'DOT physical current', 'stage' => 'compliance', 'owner' => 'driver', 'required' => true],
                [
                    'key' => 'lease_agreement', 'label' => 'Lease agreement signed',
                    'stage' => 'compliance', 'owner' => 'carrier', 'required' => true,
                    'description' => 'Written lease meeting 49 CFR Part 376.',
                ],
                [
                    'key' => 'insurance', 'label' => 'Insurance certificates on file',
                    'stage' => 'compliance', 'owner' => 'driver', 'required' => true,
                    'description' => 'Occupational accident and physical damage cover.',
                ],
                [
                    'key' => 'equipment_inspection', 'label' => 'Annual inspection verified',
                    'stage' => 'equipment', 'owner' => 'carrier', 'required' => true,
                ],
                ['key' => 'orientation', 'label' => 'Orientation completed', 'stage' => 'training', 'owner' => 'carrier', 'required' => true],
                ['key' => 'settlement_setup', 'label' => 'Settlement and payment details set up', 'stage' => 'equipment', 'owner' => 'driver', 'required' => true],
                ['key' => 'first_dispatch', 'label' => 'First dispatch', 'stage' => 'equipment', 'owner' => 'carrier', 'required' => true],
            ],
        ],
    ],

    'stages' => [
        'screening'  => 'Screening',
        'compliance' => 'Compliance',
        'training'   => 'Training',
        'equipment'  => 'Equipment & pay',
    ],
];
