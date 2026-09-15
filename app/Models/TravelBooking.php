<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelBooking extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'status'   => 'held',
        'currency' => 'USD',
    ];

    protected $casts = [
        'payload'    => 'array',
        'depart_on'  => 'date:Y-m-d',
        'departs_at' => 'datetime',
        'arrives_at' => 'datetime',
    ];

    protected $hidden = ['payload'];

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function driverProfile()
    {
        return $this->belongsTo(DriverProfile::class);
    }
}
