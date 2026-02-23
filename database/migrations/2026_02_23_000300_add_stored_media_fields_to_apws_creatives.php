<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apws_creatives', function (Blueprint $table): void {
            if (!Schema::hasColumn('apws_creatives', 'stored_media_disk')) {
                $table->string('stored_media_disk', 32)->nullable()->after('primary_file_type');
            }

            if (!Schema::hasColumn('apws_creatives', 'stored_media_path')) {
                $table->string('stored_media_path', 1024)->nullable()->index()->after('stored_media_disk');
            }

            if (!Schema::hasColumn('apws_creatives', 'stored_media_source_url')) {
                $table->text('stored_media_source_url')->nullable()->after('stored_media_path');
            }

            if (!Schema::hasColumn('apws_creatives', 'stored_media_mime')) {
                $table->string('stored_media_mime', 191)->nullable()->after('stored_media_source_url');
            }

            if (!Schema::hasColumn('apws_creatives', 'stored_media_size')) {
                $table->unsignedBigInteger('stored_media_size')->nullable()->after('stored_media_mime');
            }

            if (!Schema::hasColumn('apws_creatives', 'stored_media_downloaded_at')) {
                $table->timestamp('stored_media_downloaded_at')->nullable()->after('stored_media_size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('apws_creatives', function (Blueprint $table): void {
            if (Schema::hasColumn('apws_creatives', 'stored_media_downloaded_at')) {
                $table->dropColumn('stored_media_downloaded_at');
            }

            if (Schema::hasColumn('apws_creatives', 'stored_media_size')) {
                $table->dropColumn('stored_media_size');
            }

            if (Schema::hasColumn('apws_creatives', 'stored_media_mime')) {
                $table->dropColumn('stored_media_mime');
            }

            if (Schema::hasColumn('apws_creatives', 'stored_media_source_url')) {
                $table->dropColumn('stored_media_source_url');
            }

            if (Schema::hasColumn('apws_creatives', 'stored_media_path')) {
                $table->dropColumn('stored_media_path');
            }

            if (Schema::hasColumn('apws_creatives', 'stored_media_disk')) {
                $table->dropColumn('stored_media_disk');
            }
        });
    }
};

