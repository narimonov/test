<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
    ];

    public const ROLE_DRIVER   = 'driver';
    public const ROLE_CARRIER  = 'carrier';
    public const ROLE_ADMIN    = 'admin';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
        'verification_code_expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at'            => 'datetime',
        'phone_verified_at'            => 'datetime',
        'verification_code_expires_at' => 'datetime',
    ];

    protected $appends = ['is_verified'];
    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function carrier()
    {
        return $this->hasOne(Carrier::class);
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    /**
     * Telefon YOKI email tasdiqlansa yetarli.
     */
    public function getIsVerifiedAttribute(): bool
    {
        return $this->email_verified_at !== null || $this->phone_verified_at !== null;
    }

    public function isDriver(): bool
    {
        return $this->role === self::ROLE_DRIVER;
    }

    public function isCarrier(): bool
    {
        return $this->role === self::ROLE_CARRIER;
    }
}
