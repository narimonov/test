<?php

namespace App\Services;

use App\Models\BlacklistAppeal;
use App\Models\Carrier;
use App\Models\DriverProfile;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Counts reviews and blacklists whoever crosses the threshold.
 *
 * The rule is the same for both sides: whoever collects enough unsatisfactory
 * reviews — driver or carrier — ends up blacklisted.
 */
class ReputationService
{
    public function negativeThreshold(): int
    {
        return (int) config('reputation.blacklist_after_negative', 3);
    }

    public function isNegative(int $rating): bool
    {
        return $rating <= (int) config('reputation.negative_rating_at_or_below', 2);
    }

    /**
     * Review statistics for one subject.
     *
     * @param  DriverProfile|Carrier  $subject
     */
    public function stats(Model $subject): array
    {
        $reviews = $this->reviewQuery($subject)->get();
        $counted = $this->countableReviews($subject, $reviews);

        return [
            'total'            => $reviews->count(),
            'average'          => $reviews->count() ? round($reviews->avg('rating'), 2) : null,
            'negative'         => $counted->count(),
            'threshold'        => $this->negativeThreshold(),
            'remaining'        => max(0, $this->negativeThreshold() - $counted->count()),
            'is_blacklisted'   => $subject->blacklisted_at !== null,
            'breakdown'        => collect(range(1, 5))
                ->mapWithKeys(fn ($star) => [$star => $reviews->where('rating', $star)->count()])
                ->all(),
        ];
    }

    /**
     * Called after a review is published. Blacklists once the threshold is
     * crossed.
     *
     * @param  DriverProfile|Carrier  $subject
     */
    public function evaluate(Model $subject): bool
    {
        if ($subject->blacklisted_at !== null) {
            return true;
        }

        $negative = $this->countableReviews($subject, $this->reviewQuery($subject)->get())->count();

        if ($negative < $this->negativeThreshold()) {
            return false;
        }

        $subject->forceFill([
            'blacklisted_at'   => now(),
            'blacklist_reason' => "{$negative} unsatisfactory reviews",
        ])->save();

        Log::info('Subject blacklisted', [
            'type'     => $subject instanceof DriverProfile ? 'driver' : 'carrier',
            'id'       => $subject->id,
            'negative' => $negative,
        ]);

        return true;
    }

    /** Appeal approved: lift the blacklist and reset the count. */
    public function liftBlacklist(Model $subject, string $note = null): Model
    {
        $subject->forceFill([
            'blacklisted_at'       => null,
            'blacklist_reason'     => $note,
            'blacklist_cleared_at' => config('reputation.reset_counter_on_appeal') ? now() : $subject->blacklist_cleared_at,
        ])->save();

        return $subject->fresh();
    }

    /** An admin can also blacklist directly. */
    public function blacklist(Model $subject, string $reason): Model
    {
        $subject->forceFill([
            'blacklisted_at'   => now(),
            'blacklist_reason' => $reason,
        ])->save();

        return $subject->fresh();
    }

    public function hasPendingAppeal(Model $subject): bool
    {
        return BlacklistAppeal::pending()
            ->where($subject instanceof DriverProfile ? 'driver_profile_id' : 'carrier_id', $subject->id)
            ->exists();
    }

    // ------------------------------------------------------------------

    protected function reviewQuery(Model $subject)
    {
        return Review::published()->where(
            $subject instanceof DriverProfile ? 'driver_profile_id' : 'carrier_id',
            $subject->id
        );
    }

    /**
     * Negative reviews from before an appeal do not count, or the account
     * would be re-listed the moment it was cleared.
     */
    protected function countableReviews(Model $subject, $reviews)
    {
        return $reviews
            ->where('is_negative', true)
            ->when(
                $subject->blacklist_cleared_at !== null,
                fn ($items) => $items->filter(fn (Review $review) => $review->created_at->greaterThan($subject->blacklist_cleared_at))
            );
    }
}
