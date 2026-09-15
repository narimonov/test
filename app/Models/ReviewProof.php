<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewProof extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path'];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }
}
