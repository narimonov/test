<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobPost;
use App\Services\JobMatchingService;
use Illuminate\Http\Request;

/**
 * Drivers that fit a job post's requirements but have not applied to it.
 */
class MatchingController extends Controller
{
    public function __invoke(Request $request, JobPost $jobPost, JobMatchingService $matching)
    {
        $carrier = $request->user()->carrier;

        abort_unless($jobPost->carrier_id === optional($carrier)->id, 403, 'This job is not yours.');

        $options = $request->validate([
            'min_score'  => ['nullable', 'integer', 'min:0', 'max:100'],
            'same_state' => ['nullable', 'boolean'],
            'limit'      => ['nullable', 'integer', 'min:5', 'max:200'],
        ]);

        return response()->json($matching->matchesFor($jobPost, $options));
    }
}
