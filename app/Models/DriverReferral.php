<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverReferral extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'sent',
    ];

    public function recruitingRequest()
    {
        return $this->belongsTo(RecruitingRequest::class);
    }

    public function driverProfile()
    {
        return $this->belongsTo(DriverProfile::class);
    }
}
