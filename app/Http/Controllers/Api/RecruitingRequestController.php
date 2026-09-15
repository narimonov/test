<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\RecruitingRequest;
use App\Services\PlanGate;
use Illuminate\Http\Request;

/**
 * Personal recruiting, included in the Pro plan. The carrier writes a brief;
 * our recruiter works it in a conversation and refers drivers.
 */
class RecruitingRequestController extends Controller
{
    public function index(Request $request)
    {
        $carrier = $request->user()->carrier;

        return response()->json([
            'requests' => RecruitingRequest::where('carrier_id', $carrier->id)
                ->with(['jobPost:id,title', 'referrals.driverProfile:id,first_name,last_name,city,state,years_experience'])
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request, PlanGate $plans)
    {
        $data = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'brief'          => ['required', 'string', 'max:5000'],
            'drivers_needed' => ['nullable', 'integer', 'min:1', 'max:200'],
            'job_post_id'    => ['nullable', 'integer', 'exists:job_posts,id'],
        ]);

        $carrier = $request->user()->carrier;

        if (! $plans->allows($carrier, 'personal_recruiting')) {
            return response()->json([
                'message' => 'Personal recruiting is part of the Pro plan.',
                'code'    => 'upgrade_required',
            ], 402);
        }

        // Each request gets its own thread with the recruiter.
        $conversation = Conversation::create([
            'type'       => Conversation::TYPE_RECRUITING,
            'carrier_id' => $carrier->id,
            'subject'    => $data['title'],
        ]);

        $conversation->participants()->create(['user_id' => $request->user()->id]);

        $recruitingRequest = RecruitingRequest::create($data + [
            'carrier_id'      => $carrier->id,
            'conversation_id' => $conversation->id,
        ]);

        return response()->json([
            'message' => 'Request received. A recruiter will pick it up and reply in the thread.',
            'request' => $recruitingRequest->fresh(),
        ], 201);
    }
}
