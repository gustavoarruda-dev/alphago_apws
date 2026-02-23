<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApwsCustomerCredential extends Model
{
    protected $table = 'apws_customer_credentials';

    protected $fillable = [
        'customer_uuid',
        'provider_cod',
    ];

    protected $casts = [
        'provider_cod' => 'encrypted',
    ];
}

