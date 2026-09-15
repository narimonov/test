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
     * Migratsiyadagi default qiymatlar.
     *
     * Bularsiz yangi yaratilgan (hali bazadan qayta o'qilmagan) model
     * scoring'ga null qiymatlar bilan tushadi va noto'g'ri knockout beradi.
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
        // Y-m-d formati <input type="date"> kutgan formatga to'g'ri keladi;
        // to'liq ISO sana bilan brauzer maydonni bo'sh ko'rsatadi.
        'date_of_birth'            => 'date:Y-m-d',
        'cdl_issued_at'            => 'date:Y-m-d',
        'cdl_expires_at'           => 'date:Y-m-d',
        'medical_card_expires_at'  => 'date:Y-m-d',
        'available_from'           => 'date:Y-m-d',
        'dui_last_at'              => 'date:Y-m-d',
    ];

    protected $appends = ['full_name'];

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

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    // ------------------------------------------------------------------
    // Filtrlar — carrier talent pool'da ishlatiladi
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
