<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Repair the accounts schema on databases that were migrated before the
 * create-accounting-tables migration was amended in place. Each step is
 * guarded so this is a safe no-op on fresh installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $addedUuid = ! Schema::hasColumn('accounts', 'uuid');
        $addedParentId = ! Schema::hasColumn('accounts', 'parent_id');

        Schema::table('accounts', function (Blueprint $table) use ($addedUuid, $addedParentId) {
            if ($addedUuid) {
                $table->uuid('uuid')->nullable()->after('id');
            }
            if ($addedParentId) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('type');
            }
            if (! Schema::hasColumn('accounts', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('parent_id');
            }
            if (! Schema::hasColumn('accounts', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('accounts', 'description')) {
                $table->text('description')->nullable()->after('is_system');
            }
        });

        if ($addedUuid) {
            DB::table('accounts')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('accounts')->where('id', $row->id)->update([
                        'uuid' => (string) Str::uuid(),
                    ]);
                }
            });

            Schema::table('accounts', function (Blueprint $table) {
                $table->unique('uuid', 'accounts_uuid_unique');
            });
        }

        if ($addedParentId) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->foreign('parent_id', 'accounts_parent_id_foreign')
                    ->references('id')->on('accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            try {
                $table->dropForeign('accounts_parent_id_foreign');
            } catch (Throwable) {
                // ignore
            }
            try {
                $table->dropUnique('accounts_uuid_unique');
            } catch (Throwable) {
                // ignore
            }

            foreach (['description', 'is_system', 'is_active', 'parent_id', 'uuid'] as $column) {
                if (Schema::hasColumn('accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
