<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Carrier;
use App\Models\DriverProfile;
use App\Models\Review;
use App\Models\ReviewProof;
use App\Services\ReputationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Two-way reviews: drivers rate carriers, carriers rate drivers.
 *
 * A review can only be left once the relationship has ended, and it is not
 * published until an admin has verified the proof behind it.
 */
class ReviewController extends Controller
{
    /** Reviews and stats for one subject. */
    public function index(Request $request, ReputationService $reputation)
    {
        $data = $request->validate([
            'subject_type' => ['required', Rule::in([Review::SUBJECT_DRIVER, Review::SUBJECT_CARRIER])],
            'subject_id'   => ['required', 'integer'],
        ]);

        $subject = $this->resolveSubject($data['subject_type'], $data['subject_id']);

        $reviews = Review::published()
            ->with('author:id,name,role')
            ->where($data['subject_type'] === Review::SUBJECT_DRIVER ? 'driver_profile_id' : 'carrier_id', $subject->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'reviews' => $reviews,
            'stats'   => $reputation->stats($subject),
        ]);
    }

    /**
     * Leaving a review requires evidence that the two sides actually worked
     * together. Nothing is published here — the review waits for an admin to
     * check the proof and contact the other party.
     */
    public function store(Request $request, ReputationService $reputation)
    {
        $data = $request->validate([
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'rating'         => ['required', 'integer', 'min:1', 'max:5'],
            'body'           => ['nullable', 'string', 'max:3000'],

            'proofs'          => ['required', 'array', 'min:1', 'max:5'],
            'proofs.*'        => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'proof_kinds'     => ['nullable', 'array'],
            'proof_kinds.*'   => ['string', Rule::in(['rate_confirmation', 'employment_letter', 'settlement', 'paystub', 'other'])],
            'proof_note'      => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $application = Application::with(['jobPost.carrier', 'driverProfile'])->findOrFail($data['application_id']);

        $this->assertRelationshipExists($user, $application);

        // Whoever writes the review is rating the other side.
        $subjectType = $user->isCarrier() ? Review::SUBJECT_DRIVER : Review::SUBJECT_CARRIER;

        $alreadyReviewed = Review::where('author_user_id', $user->id)
            ->where('application_id', $application->id)
            ->exists();

        if ($alreadyReviewed) {
            throw ValidationException::withMessages([
                'application_id' => ['You have already reviewed this working relationship.'],
            ]);
        }

        $review = Review::create([
            'author_user_id'    => $user->id,
            'subject_type'      => $subjectType,
            'driver_profile_id' => $subjectType === Review::SUBJECT_DRIVER ? $application->driver_profile_id : null,
            'carrier_id'        => $subjectType === Review::SUBJECT_CARRIER ? $application->jobPost->carrier_id : null,
            'application_id'    => $application->id,
            'rating'            => $data['rating'],
            'body'              => $data['body'] ?? null,
            'is_negative'       => $reputation->isNegative($data['rating']),
            'status'            => Review::STATUS_PENDING_REVIEW,
            'submitted_at'      => now(),
        ]);

        $kinds = $data['proof_kinds'] ?? [];

        foreach ($request->file('proofs', []) as $index => $file) {
            ReviewProof::create([
                'review_id'     => $review->id,
                'kind'          => $kinds[$index] ?? 'other',
                'path'          => $file->store("reviews/{$review->id}", config('documents.disk')),
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getClientMimeType(),
                'size_bytes'    => $file->getSize(),
                'note'          => $data['proof_note'] ?? null,
            ]);
        }

        return response()->json([
            'review'  => $review->fresh()->load('proofs'),
            'message' => 'Submitted. We will verify your proof and contact the other party before it is published.',
        ], 201);
    }

    /** Reviews the signed-in user has written, with their moderation status. */
    public function mine(Request $request)
    {
        return response()->json([
            'reviews' => Review::where('author_user_id', $request->user()->id)
                ->with('proofs')
                ->latest()
                ->get(),
        ]);
    }

    /** A proof file, readable by its author and by admins. */
    public function downloadProof(Request $request, \App\Models\ReviewProof $reviewProof)
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $reviewProof->review->author_user_id === $user->id,
            403
        );

        $disk = Storage::disk(config('documents.disk'));

        abort_unless($disk->exists($reviewProof->path), 404);

        return response()->file($disk->path($reviewProof->path), [
            'Content-Type'        => $reviewProof->mime_type,
            'Content-Disposition' => 'inline; filename="' . $reviewProof->original_name . '"',
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * A review needs a finished relationship between the two parties.
     */
    protected function assertRelationshipExists($user, Application $application): void
    {
        if (! in_array($application->status, ['hired', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'application_id' => ['You can review only after the application is closed.'],
            ]);
        }

        if ($user->isCarrier()) {
            abort_unless(
                $application->jobPost->carrier_id === optional($user->carrier)->id,
                403,
                'This application does not belong to your company.'
            );

            return;
        }

        abort_unless(
            $application->driver_profile_id === optional($user->driverProfile)->id,
            403,
            'This application is not yours.'
        );
    }

    protected function resolveSubject(string $type, int $id)
    {
        return $type === Review::SUBJECT_DRIVER
            ? DriverProfile::findOrFail($id)
            : Carrier::findOrFail($id);
    }
}
