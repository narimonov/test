<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierReputationSnapshot extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'details'    => 'array',
        'rating'     => 'float',
        'fetched_at' => 'datetime',
    ];

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }
}
