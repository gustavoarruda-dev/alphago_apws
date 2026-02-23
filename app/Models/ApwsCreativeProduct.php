<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApwsCreativeProduct extends Model
{
    protected $table = 'apws_creative_products';

    protected $fillable = [
        'creative_id',
        'product',
    ];

    public function creative(): BelongsTo
    {
        return $this->belongsTo(ApwsCreative::class, 'creative_id');
    }
}
