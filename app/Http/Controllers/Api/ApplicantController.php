<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Carrier;
use App\Models\JobPost;
use App\Services\DriverScoringService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Carrier tomoni: vakansiyaga kelgan arizalarni ball bo'yicha saralab ko'rsatadi.
 */
class ApplicantController extends Controller
{
    /**
     * Bitta vakansiyaning arizachilari, ball bo'yicha tartiblangan.
     *
     * sort = score | date | experience | safety
     */
    public function index(Request $request, JobPost $jobPost, DriverScoringService $scoring)
    {
        $carrier = $this->carrierFor($request);
        abort_unless($jobPost->carrier_id === $carrier->id, 403, 'Bu vakansiya sizniki emas.');

        $filters = $request->validate([
            'sort'           => ['nullable', Rule::in(['score', 'date', 'experience', 'safety'])],
            'status'         => ['nullable', 'string'],
            'tier'           => ['nullable', 'string'],
            'hide_rejected'  => ['nullable', 'boolean'],
            'min_score'      => ['nullable', 'integer', 'min:0', 'max:100'],
            'recalculate'    => ['nullable', 'boolean'],
        ]);

        $engine = $scoring
            ->withOverrides($carrier->scoring_overrides)
            ->withOverrides($jobPost->requirements);

        $applications = Application::with('driverProfile')
            ->where('job_post_id', $jobPost->id)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->get();

        $rows = $applications->map(function (Application $application) use ($engine, $filters) {
            // Profil ariza berilgandan keyin o'zgargan bo'lishi mumkin — qayta hisoblash opsiyasi.
            if (! empty($filters['recalculate'])) {
                $result = $engine->score($application->driverProfile);
                $application->forceFill([
                    'score'           => $result['score'],
                    'tier'            => $result['tier'],
                    'score_breakdown' => $result['breakdown'],
                    'knockouts'       => $result['knockouts'],
                ])->save();
            }

            return [
                'id'           => $application->id,
                'status'       => $application->status,
                'applied_at'   => $application->created_at,
                'cover_note'   => $application->cover_note,
                'score'        => $application->score,
                'tier'         => $application->tier,
                'disqualified' => ! empty($application->knockouts),
                'knockouts'    => $application->knockouts ?: [],
                'breakdown'    => $application->score_breakdown ?: [],
                'driver'       => $application->driverProfile,
            ];
        });

        if (! empty($filters['hide_rejected'])) {
            $rows = $rows->where('status', '!=', 'rejected');
        }

        if (isset($filters['min_score'])) {
            $rows = $rows->filter(fn ($row) => ($row['score'] ?? 0) >= $filters['min_score']);
        }

        if (! empty($filters['tier'])) {
            $rows = $rows->where('tier', $filters['tier']);
        }

        $rows = $this->sortRows($rows, $filters['sort'] ?? 'score');

        return response()->json([
            'job'        => $jobPost,
            'applicants' => $rows->values(),
            'summary'    => [
                'total'        => $rows->count(),
                'qualified'    => $rows->where('disqualified', false)->count(),
                'disqualified' => $rows->where('disqualified', true)->count(),
                'by_tier'      => $rows->groupBy('tier')->map->count(),
            ],
        ]);
    }

    public function updateStatus(Request $request, Application $application)
    {
        $carrier = $this->carrierFor($request);
        abort_unless($application->jobPost->carrier_id === $carrier->id, 403, 'Bu ariza sizniki emas.');

        $data = $request->validate([
            'status' => ['required', Rule::in(['applied', 'screening', 'interview', 'hired', 'rejected'])],
        ]);

        $application->status = $data['status'];
        $application->save();

        // Driver profilining umumiy holati ham arizadan orqada qolmasin.
        if (in_array($data['status'], ['hired', 'rejected'], true)) {
            $application->driverProfile->update(['status' => $data['status']]);
        }

        return response()->json(['application' => $application->fresh()]);
    }

    protected function sortRows($rows, string $sort)
    {
        switch ($sort) {
            case 'date':
                return $rows->sortByDesc('applied_at');

            case 'experience':
                return $rows->sortByDesc(fn ($row) => (float) ($row['driver']->years_experience ?? 0));

            case 'safety':
                return $rows->sortBy(fn ($row) => ((int) ($row['driver']->accidents_3y ?? 0) * 10)
                    + (int) ($row['driver']->moving_violations_3y ?? 0));

            case 'score':
            default:
                // Disqualified bo'lganlar har doim pastda.
                return $rows->sortBy(fn ($row) => [$row['disqualified'] ? 1 : 0, -1 * (int) ($row['score'] ?? 0)]);
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
