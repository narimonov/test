<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MvrReport extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => self::STATUS_ORDERED,
    ];

    protected $casts = [
        'payload'      => 'array',
        'ordered_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    // The raw provider payload carries the full record; it stays server-side.
    protected $hidden = ['payload'];

    protected $appends = ['is_clean', 'expires_on'];

    public const STATUS_ORDERED = 'ordered';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function driverProfile()
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function shares()
    {
        return $this->hasMany(MvrReportShare::class);
    }

    public function getIsCleanAttribute(): bool
    {
        return (int) $this->violations_count === 0
            && (int) $this->accidents_count === 0
            && (int) $this->suspensions_count === 0
            && in_array($this->licence_status, [null, 'valid'], true);
    }

    /** When this record stops being reusable and a fresh pull is needed. */
    public function getExpiresOnAttribute(): ?string
    {
        if (! $this->completed_at) {
            return null;
        }

        return $this->completed_at
            ->copy()
            ->addDays((int) config('mvr.reuse_window_days', 30))
            ->toDateString();
    }
}
