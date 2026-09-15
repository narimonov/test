<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecruitingRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'status'         => 'open',
        'drivers_needed' => 1,
    ];

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function jobPost()
    {
        return $this->belongsTo(JobPost::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function referrals()
    {
        return $this->hasMany(DriverReferral::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
