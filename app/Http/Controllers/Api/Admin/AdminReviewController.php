<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReputationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Review moderation. A review only becomes visible — and only starts counting
 * towards a blacklist — once an admin has checked the proof and confirmed the
 * other party was contacted.
 */
class AdminReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', Review::STATUS_PENDING_REVIEW);

        $reviews = Review::query()
            ->with([
                'proofs',
                'author:id,name,email,role',
                'driverProfile:id,first_name,last_name',
                'carrier:id,company_name,dot_number',
                'jobPost:id,title',
            ])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(25);

        return response()->json($reviews);
    }

    /** Record that we reached the other side — required before publishing. */
    public function markContacted(Request $request, Review $review)
    {
        $review->forceFill(['counterparty_contacted_at' => now()])->save();

        return response()->json([
            'message' => 'Marked as contacted.',
            'review'  => $review->fresh(),
        ]);
    }

    public function decide(Request $request, Review $review, ReputationService $reputation)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['publish', 'reject'])],
            'note'     => ['nullable', 'string', 'max:2000'],
        ]);

        if ($review->status !== Review::STATUS_PENDING_REVIEW) {
            return response()->json(['message' => 'This review has already been decided.'], 422);
        }

        if ($data['decision'] === 'publish' && $review->counterparty_contacted_at === null) {
            return response()->json([
                'message' => 'Contact the other party before publishing this review.',
                'code'    => 'contact_required',
            ], 422);
        }

        if ($data['decision'] === 'publish' && $review->proofs()->count() === 0) {
            return response()->json([
                'message' => 'This review has no proof attached and cannot be published.',
                'code'    => 'proof_required',
            ], 422);
        }

        $review->forceFill([
            'status'               => $data['decision'] === 'publish' ? Review::STATUS_PUBLISHED : Review::STATUS_REJECTED,
            'moderation_note'      => $data['note'] ?? null,
            'reviewed_by_user_id'  => $request->user()->id,
            'reviewed_at'          => now(),
        ])->save();

        $blacklisted = false;

        if ($review->status === Review::STATUS_PUBLISHED) {
            $subject = $review->subject_type === Review::SUBJECT_DRIVER
                ? $review->driverProfile
                : $review->carrier;

            // Only now does the review count towards the threshold.
            $blacklisted = $subject ? $reputation->evaluate($subject->fresh()) : false;
        }

        return response()->json([
            'message'     => $data['decision'] === 'publish' ? 'Review published.' : 'Review rejected.',
            'review'      => $review->fresh(),
            'blacklisted' => $blacklisted,
        ]);
    }
}
