<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApwsCreative extends Model
{
    protected $table = 'apws_creatives';

    protected $fillable = [
        'customer_uuid',
        'creative_code',
        'campaign_code',
        'media_type',
        'collect_date',
        'collect_city',
        'collect_state',
        'collect_vehicle',
        'primary_file_type',
        'raw_payload',
    ];

    protected $casts = [
        'collect_date' => 'date',
        'raw_payload' => 'array',
    ];

    public function advertisers(): HasMany
    {
        return $this->hasMany(ApwsCreativeAdvertiser::class, 'creative_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(ApwsCreativeProduct::class, 'creative_id');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(ApwsPlacement::class, 'creative_code', 'creative_code');
    }
}
