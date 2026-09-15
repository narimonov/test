<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Rules\ExperienceMatchesCdlIssueDate;
use App\Services\DriverScoringService;
use Illuminate\Http\Request;

class DriverProfileController extends Controller
{
    /** The driver's own profile. */
    public function show(Request $request, DriverScoringService $scoring)
    {
        $profile = $this->profileFor($request);
        $result = $scoring->score($profile);

        return response()->json([
            'profile'      => $profile,
            'completeness' => $this->completeness($profile),
            'self_score'   => [
                'score'        => $result['score'],
                'tier'         => $result['tier'],
                'disqualified' => $result['disqualified'],
                'knockouts'    => $result['knockouts'],
                'breakdown'    => $result['breakdown'],
            ],
        ]);
    }

    public function update(Request $request, DriverScoringService $scoring)
    {
        $profile = $this->profileFor($request);

        $data = $request->validate(static::rulesWithCdlCheck($request, false, $profile));

        $profile->fill($data)->save();

        return $this->show($request, $scoring);
    }

    /**
     * Validation for a driver profile, shared with manual recruiter entry.
     */
    public static function rules(bool $requireName = true): array
    {
        return [
            'first_name'               => [$requireName ? 'required' : 'sometimes', 'string', 'max:100'],
            'last_name'                => [$requireName ? 'required' : 'sometimes', 'string', 'max:100'],
            'email'                    => ['nullable', 'email', 'max:255'],
            'phone'                    => ['nullable', 'string', 'max:30'],
            'date_of_birth'            => ['nullable', 'date'],
            'city'                     => ['nullable', 'string', 'max:100'],
            'state'                    => ['nullable', 'string', 'size:2'],
            'zip'                      => ['nullable', 'string', 'max:10'],

            'cdl_class'                => ['nullable', 'in:A,B,C'],
            'cdl_state'                => ['nullable', 'string', 'size:2'],
            'cdl_issued_at'            => ['nullable', 'date'],
            'cdl_expires_at'           => ['nullable', 'date'],
            'endorsements'             => ['nullable', 'array'],
            'endorsements.*'           => ['string', 'in:hazmat,tanker,doubles,passenger,twic'],
            'medical_card_expires_at'  => ['nullable', 'date'],

            'years_experience'         => ['nullable', 'numeric', 'min:0', 'max:60'],
            'equipment_experience'     => ['nullable', 'array'],
            'equipment_experience.*'   => ['string', 'in:dry_van,reefer,flatbed,tanker,stepdeck,car_hauler,box_truck'],
            'driver_type'              => ['nullable', 'in:company_driver,owner_operator,lease_purchase'],
            'preferred_route'          => ['nullable', 'in:otr,regional,local,dedicated'],
            'jobs_last_3_years'        => ['nullable', 'integer', 'min:0', 'max:50'],
            'longest_tenure_months'    => ['nullable', 'integer', 'min:0', 'max:600'],
            'unemployment_gap_months'  => ['nullable', 'integer', 'min:0', 'max:600'],

            'accidents_3y'             => ['nullable', 'integer', 'min:0', 'max:50'],
            'preventable_accidents_3y' => ['nullable', 'integer', 'min:0', 'max:50'],
            'moving_violations_3y'     => ['nullable', 'integer', 'min:0', 'max:50'],
            'dui_ever'                 => ['nullable', 'boolean'],
            'dui_last_at'              => ['nullable', 'date'],
            'license_suspended_ever'   => ['nullable', 'boolean'],
            'sap_status'               => ['nullable', 'in:none,in_program,completed'],
            'failed_drug_test_ever'    => ['nullable', 'boolean'],
            'can_pass_drug_test'       => ['nullable', 'boolean'],

            'work_authorization'       => ['nullable', 'in:us_citizen,green_card,ead,other'],
            'willing_to_relocate'      => ['nullable', 'boolean'],
            'available_from'           => ['nullable', 'date'],
            'desired_pay_cents'        => ['nullable', 'integer', 'min:0'],
            'desired_pay_unit'         => ['nullable', 'in:per_mile,per_week,percentage'],

            'notes'                    => ['nullable', 'string', 'max:5000'],
            'is_searchable'            => ['nullable', 'boolean'],
        ];
    }

    protected function validated(Request $request): array
    {
        return $request->validate(static::rulesWithCdlCheck($request, false));
    }

    /**
     * Adds the rule that checks experience against the CDL issue date.
     * Falls back to the stored issue date when the request omits it.
     */
    public static function rulesWithCdlCheck(Request $request, bool $requireName, DriverProfile $existing = null): array
    {
        $rules = static::rules($requireName);

        $issuedAt = $request->input('cdl_issued_at')
            ?: optional(optional($existing)->cdl_issued_at)->toDateString();

        $rules['years_experience'][] = new ExperienceMatchesCdlIssueDate($issuedAt);

        return $rules;
    }

    protected function profileFor(Request $request): DriverProfile
    {
        return DriverProfile::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'source'     => 'self_signup',
                'first_name' => $request->user()->name,
                'last_name'  => '',
                'email'      => $request->user()->email,
                'phone'      => $request->user()->phone,
            ]
        );
    }

    /**
     * How complete the profile is, so we can nudge for the rest.
     */
    protected function completeness(DriverProfile $profile): array
    {
        $required = [
            'first_name', 'last_name', 'phone', 'city', 'state',
            'cdl_class', 'cdl_expires_at', 'years_experience',
            'equipment_experience', 'driver_type', 'preferred_route',
            'work_authorization', 'available_from',
        ];

        $missing = array_values(array_filter($required, function ($field) use ($profile) {
            $value = $profile->{$field};

            return $value === null || $value === '' || $value === [];
        }));

        return [
            'percent' => (int) round((count($required) - count($missing)) / count($required) * 100),
            'missing' => $missing,
        ];
    }
}
