<?php

/*
|--------------------------------------------------------------------------
| Driver Scoring — kriteriyalar konfiguratsiyasi
|--------------------------------------------------------------------------
|
| SIZ KRITERIYALARINGIZNI AYNAN SHU FAYLDA O'ZGARTIRASIZ. Kod tegmaydi.
|
| Ikki xil kriteriya bor:
|
|  1) knockouts  — "bu bo'lsa darrov rad".  Shart bajarilmasa driver
|                   disqualified bo'ladi, ball hisoblanmaydi.
|  2) criteria   — "ball beruvchi". Har biri weight (og'irlik) oladi,
|                   natija 0..100 oralig'ida weighted average bo'lib chiqadi.
|
| Knockout operatorlari:
|   gte, lte, gt, lt, eq, neq, in, not_in, is_true, is_false,
|   date_after_today (sana bugundan keyin bo'lishi shart)
|
| Criteria tiplari:
|   bands   — raqamli qiymat uchun pog'onalar. Har band ['min'=>,'max'=>,'points'=>]
|             (min/max ixtiyoriy, birinchi mos kelgan band ishlaydi)
|   map     — qiymat => ball ('_default' => ball fallback sifatida)
|   boolean — true_points / false_points
|   set     — json massiv (endorsements, equipment). Har mos kelgani uchun ball,
|             'points_per_match' va 'max_points' bilan cheklanadi.
|
| Qiymat null bo'lsa 'null_points' ishlatiladi (default 0).
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | 1. KNOCKOUT — darrov rad qilinadigan shartlar
    |----------------------------------------------------------------------
    | Job post o'zining requirements'i bilan bularni bekor qila oladi
    | (masalan bitta vakansiya uchun 1 yil tajriba yetarli bo'lsa).
    */
    'knockouts' => [
        [
            'key'      => 'cdl_class',
            'operator' => 'in',
            'value'    => ['A'],
            'reason'   => 'CDL Class A emas',
        ],
        [
            'key'      => 'years_experience',
            'operator' => 'gte',
            'value'    => 1,
            'reason'   => 'Tajriba 1 yildan kam',
        ],
        [
            'key'      => 'can_pass_drug_test',
            'operator' => 'is_true',
            'reason'   => 'Drug test topshira olmaydi',
        ],
        [
            'key'      => 'license_suspended_ever',
            'operator' => 'is_false',
            'reason'   => 'Litsenziya to\'xtatilgan (suspension) bo\'lgan',
        ],
        [
            'key'      => 'sap_status',
            'operator' => 'not_in',
            'value'    => ['in_program'],
            'reason'   => 'SAP dasturini tugatmagan',
        ],
        [
            'key'      => 'cdl_expires_at',
            'operator' => 'date_after_today',
            'reason'   => 'CDL muddati tugagan',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | 2. BALL BERUVCHI KRITERIYALAR
    |----------------------------------------------------------------------
    | weight — nisbiy og'irlik. Yig'indisi 100 bo'lishi shart emas,
    | tizim o'zi normalize qiladi.
    */
    'criteria' => [

        [
            'key'    => 'years_experience',
            'label'  => 'Tajriba (yil)',
            'group'  => 'experience',
            'type'   => 'bands',
            'weight' => 25,
            'bands'  => [
                ['min' => 5,   'points' => 100, 'label' => '5+ yil'],
                ['min' => 3,   'points' => 85,  'label' => '3–5 yil'],
                ['min' => 2,   'points' => 70,  'label' => '2–3 yil'],
                ['min' => 1,   'points' => 45,  'label' => '1–2 yil'],
                ['min' => 0,   'points' => 0,   'label' => '1 yildan kam'],
            ],
        ],

        [
            'key'    => 'accidents_3y',
            'label'  => 'Oxirgi 3 yildagi avariyalar',
            'group'  => 'safety',
            'type'   => 'bands',
            'weight' => 20,
            'bands'  => [
                ['max' => 0, 'points' => 100, 'label' => 'Yo\'q'],
                ['max' => 1, 'points' => 55,  'label' => '1 ta'],
                ['max' => 2, 'points' => 20,  'label' => '2 ta'],
                ['points' => 0, 'label' => '3+ ta'],
            ],
        ],

        [
            'key'    => 'moving_violations_3y',
            'label'  => 'Oxirgi 3 yildagi moving violation',
            'group'  => 'safety',
            'type'   => 'bands',
            'weight' => 12,
            'bands'  => [
                ['max' => 0, 'points' => 100, 'label' => 'Yo\'q'],
                ['max' => 1, 'points' => 75,  'label' => '1 ta'],
                ['max' => 2, 'points' => 45,  'label' => '2 ta'],
                ['max' => 3, 'points' => 15,  'label' => '3 ta'],
                ['points' => 0, 'label' => '4+ ta'],
            ],
        ],

        [
            'key'    => 'jobs_last_3_years',
            'label'  => 'Job hopping (3 yilda nechta ish)',
            'group'  => 'stability',
            'type'   => 'bands',
            'weight' => 15,
            'bands'  => [
                ['max' => 1, 'points' => 100, 'label' => '1 ta'],
                ['max' => 2, 'points' => 85,  'label' => '2 ta'],
                ['max' => 3, 'points' => 60,  'label' => '3 ta'],
                ['max' => 4, 'points' => 30,  'label' => '4 ta'],
                ['points' => 0, 'label' => '5+ ta'],
            ],
        ],

        [
            'key'    => 'longest_tenure_months',
            'label'  => 'Eng uzun ish staji (oy)',
            'group'  => 'stability',
            'type'   => 'bands',
            'weight' => 8,
            'bands'  => [
                ['min' => 24, 'points' => 100, 'label' => '2+ yil'],
                ['min' => 12, 'points' => 75,  'label' => '1–2 yil'],
                ['min' => 6,  'points' => 45,  'label' => '6–12 oy'],
                ['points' => 15, 'label' => '6 oydan kam'],
            ],
        ],

        [
            'key'    => 'unemployment_gap_months',
            'label'  => 'Ishsiz yurgan davr (oy)',
            'group'  => 'stability',
            'type'   => 'bands',
            'weight' => 6,
            'bands'  => [
                ['max' => 1, 'points' => 100, 'label' => 'Yo\'q'],
                ['max' => 3, 'points' => 70,  'label' => '1–3 oy'],
                ['max' => 6, 'points' => 40,  'label' => '3–6 oy'],
                ['points' => 10, 'label' => '6+ oy'],
            ],
        ],

        [
            'key'              => 'endorsements',
            'label'            => 'Endorsement\'lar',
            'group'            => 'qualification',
            'type'             => 'set',
            'weight'           => 8,
            'valuable'         => ['hazmat' => 40, 'tanker' => 30, 'doubles' => 20, 'twic' => 10],
            'max_points'       => 100,
        ],

        [
            'key'        => 'equipment_experience',
            'label'      => 'Equipment tajribasi',
            'group'      => 'qualification',
            'type'       => 'set',
            'weight'     => 6,
            'valuable'   => ['dry_van' => 25, 'reefer' => 30, 'flatbed' => 25, 'tanker' => 20],
            'max_points' => 100,
        ],

        [
            'key'    => 'sap_status',
            'label'  => 'SAP holati',
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
            'label'        => 'DUI bo\'lganmi',
            'group'        => 'safety',
            'type'         => 'boolean',
            'weight'       => 10,
            'true_points'  => 0,
            'false_points' => 100,
        ],

        [
            'key'    => 'work_authorization',
            'label'  => 'Ishlash huquqi',
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
    | 3. TIER — ballga qarab darajaga ajratish
    |----------------------------------------------------------------------
    */
    'tiers' => [
        'A' => 85,   // 85+  -> darrov qo'ng'iroq qilinadigan
        'B' => 70,   // 70–84
        'C' => 50,   // 50–69
        'D' => 0,    // 50 dan past
    ],
];
