<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\DriverProfile;
use App\Services\DriverScoringService;
use App\Services\PlanGate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The carrier side: filter the whole driver pool on criteria, ranked by
 * score. Recruiters can also enter drivers by hand.
 */
class TalentPoolController extends Controller
{
    public function index(Request $request, DriverScoringService $scoring, PlanGate $plans)
    {
        $filters = $request->validate([
            'q'               => ['nullable', 'string', 'max:100'],
            'state'           => ['nullable', 'string', 'size:2'],
            'cdl_class'       => ['nullable', 'in:A,B,C'],
            'driver_type'     => ['nullable', 'in:company_driver,owner_operator,lease_purchase'],
            'preferred_route' => ['nullable', 'in:otr,regional,local,dedicated'],
            'status'          => ['nullable', 'string', 'max:30'],
            'endorsement'     => ['nullable', 'string', 'max:30'],
            'equipment'       => ['nullable', 'string', 'max:30'],
            'min_experience'  => ['nullable', 'numeric', 'min:0'],
            'max_accidents'   => ['nullable', 'integer', 'min:0'],
            'max_violations'  => ['nullable', 'integer', 'min:0'],
            'max_jobs_3y'     => ['nullable', 'integer', 'min:0'],
            'no_dui'          => ['nullable', 'boolean'],
            'hide_disqualified' => ['nullable', 'boolean'],
            'include_hired'     => ['nullable', 'boolean'],
            'include_blacklisted' => ['nullable', 'boolean'],
            'min_score'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'sort'            => ['nullable', Rule::in(['score', 'date', 'experience', 'safety', 'name'])],
            'per_page'        => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $engine = $scoring->withOverrides($this->carrierFor($request)->scoring_overrides);

        $drivers = DriverProfile::query()
            ->where('is_searchable', true)
            // Drivers hired elsewhere, and blacklisted ones, are hidden by default.
            ->when(empty($filters['include_hired']), fn ($query) => $query->whereNull('hired_carrier_id'))
            ->when(empty($filters['include_blacklisted']), fn ($query) => $query->whereNull('blacklisted_at'))
            ->filter($filters)
            ->limit(500)
            ->get();

        $ranked = collect($engine->rank($drivers))->map(function (array $row) {
            return [
                'score'        => $row['score'],
                'tier'         => $row['tier'],
                'disqualified' => $row['disqualified'],
                'knockouts'    => $row['knockouts'],
                'breakdown'    => $row['breakdown'],
                'driver'       => $row['driver'],
            ];
        });

        if (! empty($filters['hide_disqualified'])) {
            $ranked = $ranked->where('disqualified', false);
        }

        if (isset($filters['min_score'])) {
            $ranked = $ranked->filter(fn ($row) => $row['score'] >= $filters['min_score']);
        }

        $ranked = $this->sortRows($ranked, $filters['sort'] ?? 'score');

        // Starter sees only the strongest slice of the pool.
        $cap = $plans->talentPoolLimit($this->carrierFor($request));
        $capped = $cap !== null && $ranked->count() > $cap;

        if ($capped) {
            $ranked = $ranked->take($cap);
        }

        $perPage = $filters['per_page'] ?? 20;
        $page = max(1, (int) $request->query('page', 1));

        return response()->json([
            'data' => $ranked->forPage($page, $perPage)->values(),
            'meta' => [
                'total'        => $ranked->count(),
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => max(1, (int) ceil($ranked->count() / $perPage)),
                'qualified'    => $ranked->where('disqualified', false)->count(),
                'by_tier'      => $ranked->groupBy('tier')->map->count(),
                'capped_by_plan' => $capped,
                'plan_limit'   => $cap,
            ],
        ]);
    }

    public function show(Request $request, DriverProfile $driverProfile, DriverScoringService $scoring)
    {
        $result = $scoring
            ->withOverrides($this->carrierFor($request)->scoring_overrides)
            ->score($driverProfile);

        return response()->json([
            'driver' => $driverProfile,
            'score'  => $result,
        ]);
    }

    /** A recruiter enters a driver by hand, e.g. after a phone screen. */
    public function store(Request $request, DriverScoringService $scoring, PlanGate $plans)
    {
        if (! $plans->allows($this->carrierFor($request), 'manual_driver_entry')) {
            return response()->json([
                'message' => 'Adding drivers manually is part of the Growth plan.',
                'code'    => 'upgrade_required',
            ], 402);
        }

        $data = $request->validate(DriverProfileController::rulesWithCdlCheck($request, true));

        $driver = DriverProfile::create($data + [
            'source'             => 'manual',
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'driver' => $driver,
            'score'  => $scoring->withOverrides($this->carrierFor($request)->scoring_overrides)->score($driver),
        ], 201);
    }

    public function update(Request $request, DriverProfile $driverProfile, DriverScoringService $scoring)
    {
        // Only manually entered drivers are editable; others own their profile.
        abort_if($driverProfile->user_id !== null, 403, 'This driver manages their own profile.');

        $driverProfile->fill(
            $request->validate(DriverProfileController::rulesWithCdlCheck($request, false, $driverProfile))
        )->save();

        return response()->json([
            'driver' => $driverProfile->fresh(),
            'score'  => $scoring->withOverrides($this->carrierFor($request)->scoring_overrides)->score($driverProfile->fresh()),
        ]);
    }

    public function updateStatus(Request $request, DriverProfile $driverProfile)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'contacted', 'screening', 'hired', 'rejected'])],
        ]);

        $driverProfile->update($data);

        return response()->json(['driver' => $driverProfile->fresh()]);
    }

    protected function sortRows($rows, string $sort)
    {
        switch ($sort) {
            case 'date':
                return $rows->sortByDesc(fn ($row) => $row['driver']->created_at);

            case 'experience':
                return $rows->sortByDesc(fn ($row) => (float) $row['driver']->years_experience);

            case 'safety':
                return $rows->sortBy(fn ($row) => ((int) $row['driver']->accidents_3y * 10)
                    + (int) $row['driver']->moving_violations_3y);

            case 'name':
                return $rows->sortBy(fn ($row) => $row['driver']->full_name);

            case 'score':
            default:
                return $rows->sortBy(fn ($row) => [$row['disqualified'] ? 1 : 0, -1 * $row['score']]);
        }
    }

    protected function carrierFor(Request $request): Carrier
    {
        return Carrier::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['company_name' => $request->user()->name]
        );
    }
}
