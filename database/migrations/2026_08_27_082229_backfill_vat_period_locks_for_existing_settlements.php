<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('journal_entries')
            ->where('type', 'vat_settlement')
            ->whereNull('vat_period_start')
            ->whereNotNull('reference')
            ->orderBy('created_at')
            ->get(['id', 'reference', 'created_at'])
            ->each(function (object $settlement): void {
                if (preg_match(
                    '/^VAT-SETTLEMENT-(\d{4}-\d{2}-\d{2})-(\d{4}-\d{2}-\d{2})(?:-v\d+)?$/',
                    (string) $settlement->reference,
                    $matches,
                ) !== 1) {
                    return;
                }

                DB::table('journal_entries')
                    ->where('id', $settlement->id)
                    ->update([
                        'vat_period_start' => $matches[1],
                        'vat_period_end' => $matches[2],
                        'vat_period_locked_at' => $settlement->created_at,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The columns are removed by the preceding schema migration.
    }
};
