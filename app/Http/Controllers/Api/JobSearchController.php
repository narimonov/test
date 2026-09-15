<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\DriverProfile;
use App\Models\JobPost;
use App\Services\DriverScoringService;
use Illuminate\Http\Request;

/**
 * The driver side: browse jobs and apply.
 */
class JobSearchController extends Controller
{
    public function index(Request $request)
    {
        $jobs = JobPost::query()
            ->with('carrier:id,company_name,city,state')
            ->where('is_open', true)
            ->filter($request->only(['q', 'state', 'route_type', 'driver_type']))
            ->latest()
            ->paginate(15);

        $appliedIds = [];
        if ($profile = $this->profileFor($request)) {
            $appliedIds = Application::where('driver_profile_id', $profile->id)
                ->pluck('job_post_id')
                ->all();
        }

        $jobs->getCollection()->transform(function (JobPost $job) use ($appliedIds) {
            $job->already_applied = in_array($job->id, $appliedIds, true);

            return $job;
        });

        return response()->json($jobs);
    }

    public function show(JobPost $jobPost)
    {
        return response()->json([
            'job' => $jobPost->load('carrier:id,company_name,city,state,about,website,fleet_size'),
        ]);
    }

    public function apply(Request $request, JobPost $jobPost, DriverScoringService $scoring)
    {
        $data = $request->validate([
            'cover_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $jobPost->is_open) {
            return response()->json(['message' => 'This job is closed.'], 422);
        }

        $profile = $this->profileFor($request);

        if (! $profile) {
            return response()->json(['message' => 'Fill in your driver profile first.'], 422);
        }

        if (Application::where('job_post_id', $jobPost->id)->where('driver_profile_id', $profile->id)->exists()) {
            return response()->json(['message' => 'You have already applied to this job.'], 422);
        }

        // The score is snapshotted at application time, so history survives later profile edits.
        $result = $scoring
            ->withOverrides($jobPost->carrier->scoring_overrides)
            ->withOverrides($jobPost->requirements)
            ->score($profile);

        $application = Application::create([
            'job_post_id'       => $jobPost->id,
            'driver_profile_id' => $profile->id,
            'cover_note'        => $data['cover_note'] ?? null,
            'score'             => $result['score'],
            'tier'              => $result['tier'],
            'score_breakdown'   => $result['breakdown'],
            'knockouts'         => $result['knockouts'],
        ]);

        return response()->json([
            'message'     => 'Application sent.',
            'application' => $application,
        ], 201);
    }

    public function applications(Request $request)
    {
        $profile = $this->profileFor($request);

        if (! $profile) {
            return response()->json(['data' => []]);
        }

        $applications = Application::query()
            ->with('jobPost.carrier:id,company_name,city,state')
            ->where('driver_profile_id', $profile->id)
            ->latest()
            ->paginate(20);

        // Drivers see the status, never the internal score.
        $applications->getCollection()->transform(function (Application $application) {
            return $application->makeHidden(['score', 'tier', 'score_breakdown', 'knockouts']);
        });

        return response()->json($applications);
    }

    protected function profileFor(Request $request): ?DriverProfile
    {
        return DriverProfile::where('user_id', $request->user()->id)->first();
    }
}
