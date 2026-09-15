<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverDocument extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'status'         => 'pending',
        'watermark_text' => 'recruiting',
    ];

    protected $casts = [
        'redactions'          => 'array',
        'document_expires_at' => 'date:Y-m-d',
        'processed_at'        => 'datetime',
    ];

    // Asl rasm yo'li hech qachon API javobiga tushmasligi kerak.
    protected $hidden = ['original_path', 'pdf_path'];

    protected $appends = ['type_label', 'is_expired'];

    public const TYPE_CDL = 'cdl';
    public const TYPE_MEDICAL_CARD = 'medical_card';

    public function driverProfile()
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return config('documents.types')[$this->type] ?? $this->type;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->document_expires_at !== null && $this->document_expires_at->isPast();
    }
}
