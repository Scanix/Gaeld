<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX webhook_calls_due_dispatch ON webhook_calls (next_retry_at, id) WHERE status = 'pending' AND next_retry_at IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS webhook_calls_due_dispatch');
    }
};
