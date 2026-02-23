<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apws_creatives', function (Blueprint $table): void {
            $table->id();
            $table->string('creative_code', 32)->unique();
            $table->string('campaign_code', 64)->nullable()->index();
            $table->string('media_type', 128)->nullable()->index();
            $table->date('collect_date')->nullable()->index();
            $table->string('collect_city', 128)->nullable()->index();
            $table->string('collect_state', 16)->nullable()->index();
            $table->string('collect_vehicle', 255)->nullable()->index();
            $table->string('primary_file_type', 16)->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('apws_creative_advertisers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('creative_id')->constrained('apws_creatives')->cascadeOnDelete();
            $table->string('advertiser', 255)->index();
            $table->timestamps();

            $table->unique(['creative_id', 'advertiser']);
        });

        Schema::create('apws_creative_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('creative_id')->constrained('apws_creatives')->cascadeOnDelete();
            $table->string('product', 255)->index();
            $table->timestamps();

            $table->unique(['creative_id', 'product']);
        });

        Schema::create('apws_placements', function (Blueprint $table): void {
            $table->id();
            $table->string('placement_external_id', 64)->unique();
            $table->string('creative_code', 32)->index();
            $table->dateTime('aired_at')->nullable()->index();
            $table->string('place_raw', 128)->nullable()->index();
            $table->string('city', 128)->nullable()->index();
            $table->string('state', 16)->nullable()->index();
            $table->string('vehicle', 255)->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('apws_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('request_t1', 15)->nullable()->index();
            $table->string('request_t2', 15)->nullable()->index();
            $table->string('response_t1', 15)->nullable()->index();
            $table->string('response_t2', 15)->nullable()->index();
            $table->string('status', 24)->default('success')->index();
            $table->unsignedSmallInteger('http_status')->default(200)->index();
            $table->text('provider_message')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('quantity_creatives')->default(0);
            $table->unsignedInteger('quantity_placements')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('apws_sync_cursors', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key', 64)->unique();
            $table->string('last_success_t2', 15)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apws_sync_cursors');
        Schema::dropIfExists('apws_sync_runs');
        Schema::dropIfExists('apws_placements');
        Schema::dropIfExists('apws_creative_products');
        Schema::dropIfExists('apws_creative_advertisers');
        Schema::dropIfExists('apws_creatives');
    }
};
