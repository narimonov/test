<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverOnboarding extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'track'  => 'company_driver',
        'status' => 'in_progress',
    ];

    protected $casts = [
        'start_date'   => 'date:Y-m-d',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['progress'];

    public function steps()
    {
        return $this->hasMany(DriverOnboardingStep::class)->orderBy('position');
    }

    public function driverProfile()
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    /**
     * Progress counts required steps only — an optional step left undone is
     * not the same as being blocked.
     */
    public function getProgressAttribute(): array
    {
        $steps = $this->relationLoaded('steps') ? $this->steps : $this->steps()->get();
        $required = $steps->where('is_required', true);
        $done = $required->whereIn('status', ['done', 'skipped'])->count();

        return [
            'required'  => $required->count(),
            'done'      => $done,
            'percent'   => $required->count() ? (int) round($done / $required->count() * 100) : 0,
            'blocked'   => $steps->where('status', 'failed')->count(),
            'next'      => optional($steps->firstWhere('status', 'pending'))->label,
        ];
    }
}
