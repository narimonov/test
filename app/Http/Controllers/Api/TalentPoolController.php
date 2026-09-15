<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\DriverProfile;
use App\Services\DriverScoringService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Recruiter/carrier tomoni: butun driver bazasini kriteriyalar bo'yicha
 * filtrlab, ball bo'yicha saralab ko'radi. Qo'lda driver ham kirita oladi.
 */
class TalentPoolController extends Controller
{
    public function index(Request $request, DriverScoringService $scoring)
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
            'min_score'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'sort'            => ['nullable', Rule::in(['score', 'date', 'experience', 'safety', 'name'])],
            'per_page'        => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $engine = $scoring->withOverrides($this->carrierFor($request)->scoring_overrides);

        $drivers = DriverProfile::query()
            ->where('is_searchable', true)
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

    /** Recruiter qo'lda driver kiritadi (telefon orqali gaplashib olgan ma'lumot). */
    public function store(Request $request, DriverScoringService $scoring)
    {
        $data = $request->validate(DriverProfileController::rules(true));

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
        // Faqat qo'lda kiritilgan driverni tahrirlash mumkin — driver o'z profilini o'zi boshqaradi.
        abort_if($driverProfile->user_id !== null, 403, 'Bu driver o\'z profilini o\'zi boshqaradi.');

        $driverProfile->fill($request->validate(DriverProfileController::rules(false)))->save();

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
