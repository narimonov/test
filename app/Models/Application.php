<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'applied',
    ];

    protected $casts = [
        'score_breakdown' => 'array',
        'knockouts'       => 'array',
    ];

    public function jobPost()
    {
        return $this->belongsTo(JobPost::class);
    }

    public function driverProfile()
    {
        return $this->belongsTo(DriverProfile::class);
    }
}
