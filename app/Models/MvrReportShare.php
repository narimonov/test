<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MvrReportShare extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'source' => 'ordered',
    ];

    public function mvrReport()
    {
        return $this->belongsTo(MvrReport::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }
}
