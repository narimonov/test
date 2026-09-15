<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlacklistAppeal extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** Migratsiyadagi default qiymatlar — yangi model ularsiz qaytmasin. */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function driverProfile()
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
