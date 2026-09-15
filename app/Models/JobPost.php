<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobPost extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'driver_type' => 'company_driver',
        'is_open'     => true,
    ];

    protected $casts = [
        'requirements' => 'array',
        'is_open'      => 'boolean',
    ];

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, function (Builder $q, $term) {
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%");
                });
            })
            ->when($filters['state'] ?? null, fn (Builder $q, $state) => $q->where('state', $state))
            ->when($filters['route_type'] ?? null, fn (Builder $q, $route) => $q->where('route_type', $route))
            ->when($filters['driver_type'] ?? null, fn (Builder $q, $type) => $q->where('driver_type', $type));
    }
}
