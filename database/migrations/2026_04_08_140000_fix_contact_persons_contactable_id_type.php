<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix contactable_id column type in contact_persons table.
 *
 * The original migration used $table->morphs() which should create an
 * unsignedBigInteger column, but production DB ended up with a uuid column.
 * This corrective migration changes it back to unsignedBigInteger to match
 * the Customer/Supplier integer primary keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        $column = DB::selectOne(
            "SELECT data_type FROM information_schema.columns WHERE table_name = 'contact_persons' AND column_name = 'contactable_id'"
        );

        // Only alter if the column is uuid (production). Skip if already bigint (fresh migrate).
        if ($column && $column->data_type === 'uuid') {
            Schema::table('contact_persons', function (Blueprint $table) {
                $table->dropIndex('contact_persons_contactable_type_contactable_id_index');
                $table->dropIndex('contact_persons_primary_idx');
            });

            DB::statement('ALTER TABLE contact_persons ALTER COLUMN contactable_id TYPE bigint USING 0');

            Schema::table('contact_persons', function (Blueprint $table) {
                $table->index(['contactable_type', 'contactable_id']);
                $table->index(['contactable_type', 'contactable_id', 'is_primary'], 'contact_persons_primary_idx');
            });
        }
    }

    public function down(): void
    {
        // No rollback — the forward state (bigint) is the correct one.
    }
};
