<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX journal_entries_organization_reference_unique '
            .'ON journal_entries (organization_id, reference) '
            .'WHERE reference IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS journal_entries_organization_reference_unique');
    }
};
