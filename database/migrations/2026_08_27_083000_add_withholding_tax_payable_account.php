<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            DB::table('accounts')->insertOrIgnore([
                'organization_id' => $organizationId,
                'code' => '2273',
                'name' => 'Withholding Tax Payable',
                'type' => 'liability',
                'is_active' => true,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')
            ->where('code', '2273')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw('1'))
                    ->from('transaction_lines')
                    ->whereColumn('transaction_lines.account_id', 'accounts.id');
            })
            ->delete();
    }
};
