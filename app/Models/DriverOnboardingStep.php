<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverOnboardingStep extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'status'      => 'pending',
        'is_required' => true,
        'owner'       => 'carrier',
    ];

    protected $casts = [
        'is_required'  => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function onboarding()
    {
        return $this->belongsTo(DriverOnboarding::class, 'driver_onboarding_id');
    }
}
