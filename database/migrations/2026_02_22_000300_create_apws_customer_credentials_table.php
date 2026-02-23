<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('apws_customer_credentials')) {
            Schema::create('apws_customer_credentials', function (Blueprint $table): void {
                $table->id();
                $table->uuid('customer_uuid')->unique();
                $table->text('provider_cod');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('apws_customer_credentials');
    }
};

