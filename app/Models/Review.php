<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        // A review is not published until its proof has been verified.
        'status'      => self::STATUS_PENDING_REVIEW,
        'is_negative' => false,
    ];

    protected $casts = [
        'is_negative'              => 'boolean',
        'submitted_at'             => 'datetime',
        'counterparty_contacted_at' => 'datetime',
        'reviewed_at'              => 'datetime',
    ];

    public const SUBJECT_DRIVER = 'driver';
    public const SUBJECT_CARRIER = 'carrier';

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REMOVED = 'removed';

    /** At or below this rating a review counts as unsatisfactory. */
    public const NEGATIVE_AT_OR_BELOW = 2;

    public function author()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function driverProfile()
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function proofs()
    {
        return $this->hasMany(ReviewProof::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeAwaitingModeration($query)
    {
        return $query->where('status', self::STATUS_PENDING_REVIEW);
    }

    public static function isNegativeRating(int $rating): bool
    {
        return $rating <= self::NEGATIVE_AT_OR_BELOW;
    }
}
