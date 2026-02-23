<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultCustomerUuid = trim((string) env('APWS_DEFAULT_CUSTOMER_UUID', '00000000-0000-0000-0000-000000000000'));

        if (!Schema::hasColumn('apws_creatives', 'customer_uuid')) {
            Schema::table('apws_creatives', function (Blueprint $table) use ($defaultCustomerUuid): void {
                $table->uuid('customer_uuid')->default($defaultCustomerUuid)->index();
            });
        }

        if (!Schema::hasColumn('apws_placements', 'customer_uuid')) {
            Schema::table('apws_placements', function (Blueprint $table) use ($defaultCustomerUuid): void {
                $table->uuid('customer_uuid')->default($defaultCustomerUuid)->index();
            });
        }

        if (!Schema::hasColumn('apws_sync_runs', 'customer_uuid')) {
            Schema::table('apws_sync_runs', function (Blueprint $table) use ($defaultCustomerUuid): void {
                $table->uuid('customer_uuid')->default($defaultCustomerUuid)->index();
            });
        }

        if (!Schema::hasColumn('apws_sync_cursors', 'customer_uuid')) {
            Schema::table('apws_sync_cursors', function (Blueprint $table) use ($defaultCustomerUuid): void {
                $table->uuid('customer_uuid')->default($defaultCustomerUuid)->index();
            });
        }

        DB::table('apws_creatives')
            ->whereNull('customer_uuid')
            ->update(['customer_uuid' => $defaultCustomerUuid]);
        DB::table('apws_placements')
            ->whereNull('customer_uuid')
            ->update(['customer_uuid' => $defaultCustomerUuid]);
        DB::table('apws_sync_runs')
            ->whereNull('customer_uuid')
            ->update(['customer_uuid' => $defaultCustomerUuid]);
        DB::table('apws_sync_cursors')
            ->whereNull('customer_uuid')
            ->update(['customer_uuid' => $defaultCustomerUuid]);

        $this->dropUniqueIfExists('apws_creatives', 'apws_creatives_creative_code_unique');
        $this->dropUniqueIfExists('apws_placements', 'apws_placements_placement_external_id_unique');
        $this->dropUniqueIfExists('apws_sync_cursors', 'apws_sync_cursors_source_key_unique');

        $this->createUniqueIfMissing('apws_creatives', function (Blueprint $table): void {
            $table->unique(['customer_uuid', 'creative_code'], 'apws_creatives_customer_uuid_creative_code_unique');
        });
        $this->createUniqueIfMissing('apws_placements', function (Blueprint $table): void {
            $table->unique(['customer_uuid', 'placement_external_id'], 'apws_placements_customer_uuid_placement_external_id_unique');
        });
        $this->createUniqueIfMissing('apws_sync_cursors', function (Blueprint $table): void {
            $table->unique(['source_key', 'customer_uuid'], 'apws_sync_cursors_source_key_customer_uuid_unique');
        });

        $this->createIndexIfMissing('apws_placements', function (Blueprint $table): void {
            $table->index(['customer_uuid', 'creative_code'], 'apws_placements_customer_uuid_creative_code_index');
        });
    }

    public function down(): void
    {
        $this->dropUniqueIfExists('apws_creatives', 'apws_creatives_customer_uuid_creative_code_unique');
        $this->dropUniqueIfExists('apws_placements', 'apws_placements_customer_uuid_placement_external_id_unique');
        $this->dropUniqueIfExists('apws_sync_cursors', 'apws_sync_cursors_source_key_customer_uuid_unique');
        $this->dropIndexIfExists('apws_placements', 'apws_placements_customer_uuid_creative_code_index');

        $this->createUniqueIfMissing('apws_creatives', function (Blueprint $table): void {
            $table->unique('creative_code', 'apws_creatives_creative_code_unique');
        });
        $this->createUniqueIfMissing('apws_placements', function (Blueprint $table): void {
            $table->unique('placement_external_id', 'apws_placements_placement_external_id_unique');
        });
        $this->createUniqueIfMissing('apws_sync_cursors', function (Blueprint $table): void {
            $table->unique('source_key', 'apws_sync_cursors_source_key_unique');
        });

        if (Schema::hasColumn('apws_sync_cursors', 'customer_uuid')) {
            Schema::table('apws_sync_cursors', function (Blueprint $table): void {
                $table->dropColumn('customer_uuid');
            });
        }

        if (Schema::hasColumn('apws_sync_runs', 'customer_uuid')) {
            Schema::table('apws_sync_runs', function (Blueprint $table): void {
                $table->dropColumn('customer_uuid');
            });
        }

        if (Schema::hasColumn('apws_placements', 'customer_uuid')) {
            Schema::table('apws_placements', function (Blueprint $table): void {
                $table->dropColumn('customer_uuid');
            });
        }

        if (Schema::hasColumn('apws_creatives', 'customer_uuid')) {
            Schema::table('apws_creatives', function (Blueprint $table): void {
                $table->dropColumn('customer_uuid');
            });
        }
    }

    private function dropUniqueIfExists(string $table, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($index): void {
                $table->dropUnique($index);
            });
        } catch (\Throwable) {
            // no-op when index does not exist
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($index): void {
                $table->dropIndex($index);
            });
        } catch (\Throwable) {
            // no-op when index does not exist
        }
    }

    private function createUniqueIfMissing(string $table, \Closure $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Throwable) {
            // no-op when index already exists
        }
    }

    private function createIndexIfMissing(string $table, \Closure $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Throwable) {
            // no-op when index already exists
        }
    }
};
