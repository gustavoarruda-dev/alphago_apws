<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApwsSyncCursor extends Model
{
    protected $table = 'apws_sync_cursors';

    protected $fillable = [
        'source_key',
        'customer_uuid',
        'last_success_t2',
    ];
}
