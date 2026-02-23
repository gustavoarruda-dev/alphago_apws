<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApwsCreativeAdvertiser extends Model
{
    protected $table = 'apws_creative_advertisers';

    protected $fillable = [
        'creative_id',
        'advertiser',
    ];

    public function creative(): BelongsTo
    {
        return $this->belongsTo(ApwsCreative::class, 'creative_id');
    }
}
