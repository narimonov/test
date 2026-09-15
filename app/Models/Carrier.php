<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carrier extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'scoring_overrides'       => 'array',
        'subscription_expires_at' => 'datetime',
    ];

    protected $appends = ['has_active_subscription'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jobPosts()
    {
        return $this->hasMany(JobPost::class);
    }

    /**
     * Carrier tomoni pullik: obuna aktiv bo'lmasa applicant/talent pool yopiq.
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
