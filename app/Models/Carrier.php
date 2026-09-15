<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carrier extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'subscription_plan'   => 'free',
        'subscription_status' => 'inactive',
        'allowed_to_operate'  => false,
    ];

    protected $casts = [
        'scoring_overrides'       => 'array',
        'fmcsa_snapshot'          => 'array',
        'allowed_to_operate'      => 'boolean',
        'subscription_expires_at' => 'datetime',
        'fmcsa_checked_at'        => 'datetime',
        'fmcsa_verified_at'       => 'datetime',
        'blocked_at'              => 'datetime',
        'blacklisted_at'          => 'datetime',
    ];

    protected $appends = ['has_active_subscription', 'is_fmcsa_verified', 'is_blocked', 'is_blacklisted'];

    /*
     * The FMCSA contacts are internal and never appear in an API response.
     * fmcsa_snapshot is hidden too, because it carries the same phone and
     * email in raw form.
     */
    protected $hidden = ['fmcsa_phone', 'fmcsa_email', 'fmcsa_snapshot'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jobPosts()
    {
        return $this->hasMany(JobPost::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class)->where('subject_type', Review::SUBJECT_CARRIER);
    }

    public function hiredDrivers()
    {
        return $this->hasMany(DriverProfile::class, 'hired_carrier_id');
    }

    public function getIsFmcsaVerifiedAttribute(): bool
    {
        return $this->fmcsa_verified_at !== null && $this->allowed_to_operate;
    }

    public function getIsBlockedAttribute(): bool
    {
        return $this->blocked_at !== null;
    }

    public function getIsBlacklistedAttribute(): bool
    {
        return $this->blacklisted_at !== null;
    }

    /**
     * Paid side: without an active plan, applicants and the pool are closed.
     */
    public function getHasActiveSubscriptionAttribute(): bool
    {
        if ($this->subscription_status !== 'active') {
            return false;
        }

        return $this->subscription_expires_at === null
            || $this->subscription_expires_at->isFuture();
    }
}
