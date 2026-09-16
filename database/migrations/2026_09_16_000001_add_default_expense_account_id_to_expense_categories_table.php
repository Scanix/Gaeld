<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->foreignId('default_expense_account_id')
                ->nullable()
                ->after('sort_order')
                ->constrained('accounts')
                ->nullOnDelete();
        });

        $now = now();

        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            DB::table('accounts')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'code' => '4000',
                'name' => 'Cost of Materials',
                'type' => 'expense',
                'is_active' => true,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $accountId = DB::table('accounts')
                ->where('organization_id', $organizationId)
                ->where('code', '4000')
                ->where('type', 'expense')
                ->where('is_active', true)
                ->value('id');

            DB::table('expense_categories')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'name' => 'Goods Purchased for Resale',
                'is_default' => true,
                'sort_order' => 9,
                'default_expense_account_id' => $accountId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($accountId !== null) {
                DB::table('expense_categories')
                    ->where('organization_id', $organizationId)
                    ->where('name', 'Goods Purchased for Resale')
                    ->whereNull('default_expense_account_id')
                    ->update([
                        'default_expense_account_id' => $accountId,
                        'updated_at' => $now,
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->dropForeign(['default_expense_account_id']);
            $table->dropColumn('default_expense_account_id');
        });
    }
};
