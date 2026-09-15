<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'author_type' => self::AUTHOR_USER,
    ];

    protected $casts = [
        'resolved_question' => 'boolean',
    ];

    public const AUTHOR_USER = 'user';
    public const AUTHOR_AI = 'ai';
    public const AUTHOR_AGENT = 'agent';
    public const AUTHOR_SYSTEM = 'system';

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }
}
