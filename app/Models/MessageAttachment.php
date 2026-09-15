<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageAttachment extends Model
{
    protected $guarded = ['id'];

    // The storage path is internal; files are served through a checked route.
    protected $hidden = ['path'];

    protected $appends = ['is_image'];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
