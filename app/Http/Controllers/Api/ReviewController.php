<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Carrier;
use App\Models\DriverProfile;
use App\Models\Review;
use App\Services\ReputationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ikki tomonlama baho: driver kompaniyaga, kompaniya driverga.
 *
 * Baho faqat haqiqiy ish munosabati bo'lgandan keyin qoldiriladi —
 * ya'ni ariza "hired" yoki "rejected" holatiga yetganda.
 */
class ReviewController extends Controller
{
    /** Subyekt bo'yicha baholar va statistika. */
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

    public function store(Request $request, ReputationService $reputation)
    {
        $data = $request->validate([
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'rating'         => ['required', 'integer', 'min:1', 'max:5'],
            'body'           => ['nullable', 'string', 'max:3000'],
        ]);

        $user = $request->user();
        $application = Application::with(['jobPost.carrier', 'driverProfile'])->findOrFail($data['application_id']);

        $this->assertRelationshipExists($user, $application);

        // Kompaniya driverga, driver kompaniyaga baho beradi.
        $subjectType = $user->isCarrier() ? Review::SUBJECT_DRIVER : Review::SUBJECT_CARRIER;

        $alreadyReviewed = Review::where('author_user_id', $user->id)
            ->where('application_id', $application->id)
            ->exists();

        if ($alreadyReviewed) {
            throw ValidationException::withMessages([
                'application_id' => ['Siz bu hamkorlik uchun allaqachon baho qoldirgansiz.'],
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
        ]);

        $subject = $subjectType === Review::SUBJECT_DRIVER
            ? $application->driverProfile
            : $application->jobPost->carrier;

        $blacklisted = $reputation->evaluate($subject->fresh());

        return response()->json([
            'review'      => $review,
            'stats'       => $reputation->stats($subject->fresh()),
            'blacklisted' => $blacklisted,
            'message'     => $blacklisted
                ? 'Baho saqlandi. Qoniqarsiz baholar soni chegaradan oshdi — subyekt blacklist\'ga tushdi.'
                : 'Baho saqlandi.',
        ], 201);
    }

    // ------------------------------------------------------------------

    /**
     * Baho qoldirish uchun tomonlar orasida yakunlangan munosabat bo'lishi shart.
     */
    protected function assertRelationshipExists($user, Application $application): void
    {
        if (! in_array($application->status, ['hired', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'application_id' => ['Baho faqat ariza yakunlangandan keyin qoldiriladi.'],
            ]);
        }

        if ($user->isCarrier()) {
            abort_unless(
                $application->jobPost->carrier_id === optional($user->carrier)->id,
                403,
                'Bu ariza sizning kompaniyangizga tegishli emas.'
            );

            return;
        }

        abort_unless(
            $application->driver_profile_id === optional($user->driverProfile)->id,
            403,
            'Bu ariza sizniki emas.'
        );
    }

    protected function resolveSubject(string $type, int $id)
    {
        return $type === Review::SUBJECT_DRIVER
            ? DriverProfile::findOrFail($id)
            : Carrier::findOrFail($id);
    }
}
