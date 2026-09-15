<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'status'   => self::STATUS_PENDING,
        'currency' => 'USD',
    ];

    protected $casts = [
        'payload' => 'array',
        'paid_at' => 'datetime',
    ];

    // Raw gateway payloads can carry customer details; keep them server-side.
    protected $hidden = ['payload'];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }
}
