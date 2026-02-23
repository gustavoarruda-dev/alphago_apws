<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApwsSyncRun extends Model
{
    protected $table = 'apws_sync_runs';

    protected $fillable = [
        'customer_uuid',
        'request_t1',
        'request_t2',
        'response_t1',
        'response_t2',
        'status',
        'http_status',
        'provider_message',
        'error_message',
        'quantity_creatives',
        'quantity_placements',
        'duration_ms',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];
}
