<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * The defaults the migration sets.
     *
     * Without these a freshly created model — not yet read back from the
     * database — reaches scoring with nulls and trips knockout rules that
     * should not fire.
     */
    protected $attributes = [
        'source'                   => 'self_signup',
        'status'                   => 'new',
        'is_searchable'            => true,
        'years_experience'         => 0,
        'driver_type'              => 'company_driver',
        'jobs_last_3_years'        => 0,
        'longest_tenure_months'    => 0,
        'unemployment_gap_months'  => 0,
        'accidents_3y'             => 0,
        'preventable_accidents_3y' => 0,
        'moving_violations_3y'     => 0,
        'dui_ever'                 => false,
        'license_suspended_ever'   => false,
        'sap_status'               => 'none',
        'failed_drug_test_ever'    => false,
        'can_pass_drug_test'       => true,
        'willing_to_relocate'      => false,
    ];

    protected $casts = [
        'endorsements'             => 'array',
        'equipment_experience'     => 'array',
        'years_experience'         => 'float',
        'dui_ever'                 => 'boolean',
        'license_suspended_ever'   => 'boolean',
        'failed_drug_test_ever'    => 'boolean',
        'can_pass_drug_test'       => 'boolean',
        'willing_to_relocate'      => 'boolean',
        'is_searchable'            => 'boolean',
        // Y-m-d is what <input type="date"> expects; a full ISO timestamp
        // leaves the field blank in the browser.
        'date_of_birth'            => 'date:Y-m-d',
        'cdl_issued_at'            => 'date:Y-m-d',
        'cdl_expires_at'           => 'date:Y-m-d',
        'medical_card_expires_at'  => 'date:Y-m-d',
        'available_from'           => 'date:Y-m-d',
        'dui_last_at'              => 'date:Y-m-d',
        'hired_at'                 => 'datetime',
        'blacklisted_at'           => 'datetime',
    ];

    protected $appends = ['full_name', 'is_hired', 'is_blacklisted', 'cdl_number_last4'];

    /*
     * The licence number is DPPA-protected personal information. It never
     * leaves the server in full — only the last four digits are exposed.
     */
    protected $hidden = ['cdl_number'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function documents()
    {
        return $this->hasMany(DriverDocument::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class)->where('subject_type', Review::SUBJECT_DRIVER);
    }

    public function hiredCarrier()
    {
        return $this->belongsTo(Carrier::class, 'hired_carrier_id');
    }

    /** Bitta kompaniya yollagan driverni boshqasi yollay olmaydi. */
    public function getIsHiredAttribute(): bool
    {
        return $this->hired_carrier_id !== null;
    }

    public function getIsBlacklistedAttribute(): bool
    {
        return $this->blacklisted_at !== null;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getCdlNumberLast4Attribute(): ?string
    {
        return $this->cdl_number ? substr($this->cdl_number, -4) : null;
    }

    public function mvrReports()
    {
        return $this->hasMany(\App\Models\MvrReport::class);
    }

    public function onboardings()
    {
        return $this->hasMany(\App\Models\DriverOnboarding::class);
    }

    // ------------------------------------------------------------------
    // Filters used by the carrier talent pool
    // ------------------------------------------------------------------

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, function (Builder $q, $term) {
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%");
                });
            })
            ->when($filters['state'] ?? null, fn (Builder $q, $state) => $q->where('state', $state))
            ->when($filters['cdl_class'] ?? null, fn (Builder $q, $class) => $q->where('cdl_class', $class))
            ->when($filters['driver_type'] ?? null, fn (Builder $q, $type) => $q->where('driver_type', $type))
            ->when($filters['preferred_route'] ?? null, fn (Builder $q, $route) => $q->where('preferred_route', $route))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when(isset($filters['min_experience']), fn (Builder $q) => $q->where('years_experience', '>=', $filters['min_experience']))
            ->when(isset($filters['max_accidents']), fn (Builder $q) => $q->where('accidents_3y', '<=', $filters['max_accidents']))
            ->when(isset($filters['max_violations']), fn (Builder $q) => $q->where('moving_violations_3y', '<=', $filters['max_violations']))
            ->when(isset($filters['max_jobs_3y']), fn (Builder $q) => $q->where('jobs_last_3_years', '<=', $filters['max_jobs_3y']))
            ->when(! empty($filters['no_dui']), fn (Builder $q) => $q->where('dui_ever', false))
            ->when(! empty($filters['endorsement']), function (Builder $q) use ($filters) {
                $q->where('endorsements', 'like', '%"' . $filters['endorsement'] . '"%');
            })
            ->when(! empty($filters['equipment']), function (Builder $q) use ($filters) {
                $q->where('equipment_experience', 'like', '%"' . $filters['equipment'] . '"%');
            });
    }
}
