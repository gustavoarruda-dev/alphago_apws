<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApwsPlacement extends Model
{
    protected $table = 'apws_placements';

    protected $fillable = [
        'customer_uuid',
        'placement_external_id',
        'creative_code',
        'aired_at',
        'place_raw',
        'city',
        'state',
        'vehicle',
        'raw_payload',
    ];

    protected $casts = [
        'aired_at' => 'datetime',
        'raw_payload' => 'array',
    ];
}
