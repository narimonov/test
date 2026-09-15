<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'type'   => self::TYPE_HIRING,
        'status' => 'open',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public const TYPE_HIRING = 'hiring';
    public const TYPE_SUPPORT = 'support';
    public const TYPE_RECRUITING = 'recruiting';

    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_participants')->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function driverProfile()
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function jobPost()
    {
        return $this->belongsTo(JobPost::class);
    }

    /** A human agent has taken over this support chat. */
    public function isEscalated(): bool
    {
        return $this->messages()
            ->whereIn('author_type', [Message::AUTHOR_AGENT, Message::AUTHOR_SYSTEM])
            ->exists();
    }

    public function includes(User $user): bool
    {
        return $this->participants()->where('user_id', $user->id)->exists();
    }
}
