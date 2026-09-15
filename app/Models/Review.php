<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'status'      => 'published',
        'is_negative' => false,
    ];

    protected $casts = [
        'is_negative' => 'boolean',
    ];

    public const SUBJECT_DRIVER = 'driver';
    public const SUBJECT_CARRIER = 'carrier';

    /** Shu balldan past baho "qoniqarsiz" hisoblanadi. */
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

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public static function isNegativeRating(int $rating): bool
    {
        return $rating <= self::NEGATIVE_AT_OR_BELOW;
    }
}
