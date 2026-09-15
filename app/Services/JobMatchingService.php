<?php

namespace App\Services;

use App\Models\Application;
use App\Models\DriverProfile;
use App\Models\JobPost;

/**
 * Finds drivers that match a job post's requirements.
 *
 * The carrier writes requirements once on the job; the same requirements drive
 * both the ranking of people who apply and this list of people who have not.
 */
class JobMatchingService
{
    /** @var DriverScoringService */
    protected $scoring;

    public function __construct(DriverScoringService $scoring)
    {
        $this->scoring = $scoring;
    }

    /**
     * @return array{matches: array, summary: array}
     */
    public function matchesFor(JobPost $job, array $options = []): array
    {
        $carrier = $job->carrier;
        $limit = $options['limit'] ?? 50;

        $engine = $this->scoring
            ->withOverrides($carrier->scoring_overrides)
            ->withOverrides($job->requirements);

        $alreadyApplied = Application::where('job_post_id', $job->id)
            ->pluck('driver_profile_id')
            ->all();

        $drivers = DriverProfile::query()
            ->where('is_searchable', true)
            ->whereNull('hired_carrier_id')
            ->whereNull('blacklisted_at')
            ->when($alreadyApplied, fn ($query) => $query->whereNotIn('id', $alreadyApplied))
            // Pre-filter on the job's own fields so we score a sane number of rows.
            ->when($job->state && ! empty($options['same_state']), fn ($query) => $query->where('state', $job->state))
            ->when($job->route_type, fn ($query) => $query->where(function ($inner) use ($job) {
                $inner->where('preferred_route', $job->route_type)->orWhereNull('preferred_route');
            }))
            ->when($job->driver_type, fn ($query) => $query->where('driver_type', $job->driver_type))
            ->limit(500)
            ->get();

        $ranked = collect($this->scoring->withOverrides($carrier->scoring_overrides)->rank($drivers));

        // Only people who clear this job's knockouts are a match.
        $matches = collect($engine->rank($drivers))
            ->filter(fn (array $row) => ! $row['disqualified'])
            ->when(isset($options['min_score']), fn ($rows) => $rows->filter(fn ($row) => $row['score'] >= $options['min_score']))
            ->take($limit)
            ->map(fn (array $row) => [
                'score'     => $row['score'],
                'tier'      => $row['tier'],
                'breakdown' => $row['breakdown'],
                'driver'    => $row['driver'],
            ])
            ->values();

        return [
            'matches' => $matches->all(),
            'summary' => [
                'considered'  => $drivers->count(),
                'matched'     => $matches->count(),
                'top_tier'    => $matches->where('tier', 'A')->count(),
                'excluded'    => $ranked->where('disqualified', true)->count(),
                'requirements' => $this->describeRequirements($job),
            ],
        ];
    }

    /** Human-readable version of the job's knockout rules, for the UI. */
    public function describeRequirements(JobPost $job): array
    {
        $rules = $job->requirements['knockouts'] ?? [];

        return array_map(fn (array $rule) => [
            'key'    => $rule['key'],
            'label'  => $rule['reason'] ?? $rule['key'],
            'value'  => $rule['value'] ?? null,
        ], $rules);
    }
}
