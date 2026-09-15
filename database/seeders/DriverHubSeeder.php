<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Carrier;
use App\Models\DriverProfile;
use App\Models\JobPost;
use App\Models\User;
use App\Services\DriverScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo ma'lumot — ilovani ochib darrov ko'rish uchun.
 *
 *   php artisan migrate:fresh --seed
 */
class DriverHubSeeder extends Seeder
{
    public function run()
    {
        $carrierUser = User::updateOrCreate(
            ['email' => 'carrier@example.com'],
            [
                'name'              => 'Sardor Recruiter',
                'password'          => Hash::make('password'),
                'role'              => User::ROLE_CARRIER,
                'phone'             => '+15551110001',
                'email_verified_at' => now(),
            ]
        );

        $carrier = Carrier::updateOrCreate(
            ['user_id' => $carrierUser->id],
            [
                'company_name'            => 'Silk Road Logistics',
                'mc_number'               => 'MC-998877',
                'dot_number'              => 'DOT-3344556',
                'contact_name'            => 'Sardor Recruiter',
                'contact_phone'           => '+15551110001',
                'city'                    => 'Chicago',
                'state'                   => 'IL',
                'fleet_size'              => 62,
                'about'                   => 'Midwest hududida ishlaydigan reefer va dry van kompaniyasi.',
                'subscription_plan'       => 'pro',
                'subscription_status'     => 'active',
                'subscription_expires_at' => now()->addYear(),

                // Demo uchun FMCSA tekshiruvi o'tgan deb belgilanadi.
                'fmcsa_legal_name'        => 'SILK ROAD LOGISTICS LLC',
                'fmcsa_status'            => 'A',
                'allowed_to_operate'      => true,
                'fmcsa_phone'             => '+15551110001',
                'fmcsa_email'             => 'dispatch@silkroad.example',
                'fmcsa_checked_at'        => now(),
                'fmcsa_verified_at'       => now(),
            ]
        );

        $job = JobPost::updateOrCreate(
            ['carrier_id' => $carrier->id, 'title' => 'OTR CDL-A Driver — Reefer'],
            [
                'description'   => "Midwest–West Coast yo'nalishi. Haftada 2500–3000 mile. "
                    . "Yangi Freightliner Cascadia, APU bor. Har 3 haftada uyga.",
                'city'          => 'Chicago',
                'state'         => 'IL',
                'route_type'    => 'otr',
                'driver_type'   => 'company_driver',
                'equipment'     => 'Reefer',
                'pay_min_cents' => 62,
                'pay_max_cents' => 70,
                'pay_unit'      => 'per_mile',
                'is_open'       => true,
            ]
        );

        JobPost::updateOrCreate(
            ['carrier_id' => $carrier->id, 'title' => 'Regional Dry Van — Home Weekly'],
            [
                'description'   => "IL, IN, WI, MI hududi. Har hafta oxiri uyda.",
                'city'          => 'Joliet',
                'state'         => 'IL',
                'route_type'    => 'regional',
                'driver_type'   => 'company_driver',
                'equipment'     => 'Dry Van',
                'pay_min_cents' => 130000,
                'pay_max_cents' => 165000,
                'pay_unit'      => 'per_week',
                'is_open'       => true,
            ]
        );

        // Driver o'zi ro'yxatdan o'tgani
        $driverUser = User::updateOrCreate(
            ['email' => 'driver@example.com'],
            [
                'name'              => 'Jasur Tashkentov',
                'password'          => Hash::make('password'),
                'role'              => User::ROLE_DRIVER,
                'phone'             => '+15552220002',
                'email_verified_at' => now(),
            ]
        );

        $drivers = collect($this->driverData())->map(function (array $attributes, int $index) use ($driverUser, $carrierUser) {
            return DriverProfile::updateOrCreate(
                ['email' => $attributes['email']],
                $attributes + [
                    // Birinchisi — login qila oladigan haqiqiy driver akkaunti.
                    'user_id'            => $index === 0 ? $driverUser->id : null,
                    'created_by_user_id' => $index === 0 ? null : $carrierUser->id,
                    'source'             => $index === 0 ? 'self_signup' : 'manual',
                ]
            );
        });

        $scoring = app(DriverScoringService::class);

        // Birinchi vakansiyaga arizalar
        foreach ($drivers->take(6) as $driver) {
            $result = $scoring->score($driver);

            Application::updateOrCreate(
                ['job_post_id' => $job->id, 'driver_profile_id' => $driver->id],
                [
                    'score'           => $result['score'],
                    'tier'            => $result['tier'],
                    'score_breakdown' => $result['breakdown'],
                    'knockouts'       => $result['knockouts'],
                ]
            );
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'              => 'Platform Admin',
                'password'          => Hash::make('password'),
                'role'              => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Demo akkauntlar:');
        $this->command->info('  Admin:     admin@example.com / password');
        $this->command->info('  Kompaniya: carrier@example.com / password');
        $this->command->info('  Driver:    driver@example.com / password');
    }

    protected function driverData(): array
    {
        return [
            [
                'first_name' => 'Jasur', 'last_name' => 'Tashkentov',
                'email' => 'driver@example.com', 'phone' => '+15552220002',
                'city' => 'Chicago', 'state' => 'IL', 'zip' => '60616',
                'cdl_class' => 'A', 'cdl_state' => 'IL', 'cdl_expires_at' => now()->addYears(3),
                'endorsements' => ['hazmat', 'tanker'], 'equipment_experience' => ['dry_van', 'reefer'],
                'years_experience' => 6.5, 'driver_type' => 'company_driver', 'preferred_route' => 'otr',
                'jobs_last_3_years' => 1, 'longest_tenure_months' => 48, 'unemployment_gap_months' => 0,
                'accidents_3y' => 0, 'moving_violations_3y' => 0,
                'work_authorization' => 'us_citizen', 'available_from' => now()->addWeek(),
            ],
            [
                'first_name' => 'Michael', 'last_name' => 'Reyes',
                'email' => 'm.reyes@example.com', 'phone' => '+15553330003',
                'city' => 'Indianapolis', 'state' => 'IN',
                'cdl_class' => 'A', 'cdl_state' => 'IN', 'cdl_expires_at' => now()->addYears(2),
                'endorsements' => ['doubles'], 'equipment_experience' => ['dry_van', 'flatbed'],
                'years_experience' => 3.0, 'driver_type' => 'company_driver', 'preferred_route' => 'regional',
                'jobs_last_3_years' => 3, 'longest_tenure_months' => 18, 'unemployment_gap_months' => 2,
                'accidents_3y' => 1, 'moving_violations_3y' => 1,
                'work_authorization' => 'green_card', 'available_from' => now(),
            ],
            [
                'first_name' => 'Dilshod', 'last_name' => 'Karimov',
                'email' => 'd.karimov@example.com', 'phone' => '+15554440004',
                'city' => 'Milwaukee', 'state' => 'WI',
                'cdl_class' => 'A', 'cdl_state' => 'WI', 'cdl_expires_at' => now()->addYears(4),
                'endorsements' => ['hazmat', 'tanker', 'twic'], 'equipment_experience' => ['tanker', 'reefer'],
                'years_experience' => 9.0, 'driver_type' => 'owner_operator', 'preferred_route' => 'otr',
                'jobs_last_3_years' => 2, 'longest_tenure_months' => 36, 'unemployment_gap_months' => 1,
                'accidents_3y' => 0, 'moving_violations_3y' => 2,
                'work_authorization' => 'us_citizen', 'available_from' => now()->addDays(14),
            ],
            [
                'first_name' => 'Robert', 'last_name' => 'Chen',
                'email' => 'r.chen@example.com', 'phone' => '+15555550005',
                'city' => 'Detroit', 'state' => 'MI',
                'cdl_class' => 'A', 'cdl_state' => 'MI', 'cdl_expires_at' => now()->addYear(),
                'endorsements' => [], 'equipment_experience' => ['dry_van'],
                'years_experience' => 1.5, 'driver_type' => 'company_driver', 'preferred_route' => 'local',
                'jobs_last_3_years' => 5, 'longest_tenure_months' => 7, 'unemployment_gap_months' => 4,
                'accidents_3y' => 2, 'moving_violations_3y' => 3,
                'work_authorization' => 'ead', 'available_from' => now(),
            ],
            [
                'first_name' => 'Anvar', 'last_name' => 'Yusupov',
                'email' => 'a.yusupov@example.com', 'phone' => '+15556660006',
                'city' => 'Columbus', 'state' => 'OH',
                'cdl_class' => 'A', 'cdl_state' => 'OH', 'cdl_expires_at' => now()->addYears(2),
                'endorsements' => ['hazmat'], 'equipment_experience' => ['flatbed', 'stepdeck'],
                'years_experience' => 4.0, 'driver_type' => 'company_driver', 'preferred_route' => 'regional',
                'jobs_last_3_years' => 2, 'longest_tenure_months' => 26, 'unemployment_gap_months' => 0,
                'accidents_3y' => 1, 'moving_violations_3y' => 0,
                'work_authorization' => 'green_card', 'available_from' => now()->addDays(30),
            ],
            [
                // Knockout misoli: DUI + suspension + drug test.
                'first_name' => 'Kevin', 'last_name' => 'Doyle',
                'email' => 'k.doyle@example.com', 'phone' => '+15557770007',
                'city' => 'St. Louis', 'state' => 'MO',
                'cdl_class' => 'A', 'cdl_state' => 'MO', 'cdl_expires_at' => now()->addYears(3),
                'endorsements' => [], 'equipment_experience' => ['dry_van'],
                'years_experience' => 7.0, 'driver_type' => 'company_driver', 'preferred_route' => 'otr',
                'jobs_last_3_years' => 4, 'longest_tenure_months' => 14, 'unemployment_gap_months' => 8,
                'accidents_3y' => 1, 'moving_violations_3y' => 2,
                'dui_ever' => true, 'license_suspended_ever' => true, 'sap_status' => 'in_program',
                'can_pass_drug_test' => false,
                'work_authorization' => 'us_citizen',
            ],
            [
                'first_name' => 'Sarah', 'last_name' => 'Johnson',
                'email' => 's.johnson@example.com', 'phone' => '+15558880008',
                'city' => 'Madison', 'state' => 'WI',
                'cdl_class' => 'A', 'cdl_state' => 'WI', 'cdl_expires_at' => now()->addYears(5),
                'endorsements' => ['tanker', 'doubles'], 'equipment_experience' => ['reefer', 'dry_van'],
                'years_experience' => 12.0, 'driver_type' => 'company_driver', 'preferred_route' => 'dedicated',
                'jobs_last_3_years' => 1, 'longest_tenure_months' => 96, 'unemployment_gap_months' => 0,
                'accidents_3y' => 0, 'moving_violations_3y' => 0,
                'work_authorization' => 'us_citizen', 'available_from' => now()->addDays(45),
            ],
            [
                'first_name' => 'Bekzod', 'last_name' => 'Rahimov',
                'email' => 'b.rahimov@example.com', 'phone' => '+15559990009',
                'city' => 'Cleveland', 'state' => 'OH',
                'cdl_class' => 'B', 'cdl_state' => 'OH', 'cdl_expires_at' => now()->addYears(2),
                'endorsements' => [], 'equipment_experience' => ['box_truck'],
                'years_experience' => 2.0, 'driver_type' => 'company_driver', 'preferred_route' => 'local',
                'jobs_last_3_years' => 2, 'longest_tenure_months' => 12, 'unemployment_gap_months' => 1,
                'accidents_3y' => 0, 'moving_violations_3y' => 1,
                'work_authorization' => 'ead', 'available_from' => now(),
            ],
        ];
    }
}
