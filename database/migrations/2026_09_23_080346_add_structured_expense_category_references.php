<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->string('code')->nullable()->after('name');
            $table->string('translation_key')->nullable()->after('code');
            $table->boolean('is_active')->default(true)->after('is_default');
            $table->boolean('is_system')->default(false)->after('is_active');
            $table->timestamp('deactivated_at')->nullable()->after('default_expense_account_id');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->uuid('expense_category_id')->nullable()->after('category');
            $table->unsignedBigInteger('expense_account_id')->nullable()->after('expense_account_code');
            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->nullOnDelete();
            $table->foreign('expense_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->index(['organization_id', 'expense_category_id']);
            $table->index(['organization_id', 'expense_account_id']);
        });

        Schema::table('recurring_expenses', function (Blueprint $table): void {
            $table->uuid('expense_category_id')->nullable()->after('category');
            $table->unsignedBigInteger('expense_account_id')->nullable()->after('expense_account_code');
            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->nullOnDelete();
            $table->foreign('expense_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->index(['organization_id', 'expense_category_id']);
            $table->index(['organization_id', 'expense_account_id']);
        });

        $categoryCodes = [
            'Office Supplies' => 'office_supplies',
            'Travel' => 'travel',
            'Software' => 'software',
            'Professional Services' => 'professional_services',
            'Marketing' => 'marketing',
            'Rent' => 'rent',
            'Utilities' => 'utilities',
            'Insurance' => 'insurance',
            'Other' => 'other',
            'Goods Purchased for Resale' => 'goods_purchased_for_resale',
        ];

        foreach ($categoryCodes as $name => $code) {
            DB::table('expense_categories')
                ->where('name', $name)
                ->update([
                    'code' => $code,
                    'translation_key' => 'cat_'.$code,
                    'is_system' => true,
                    'is_active' => true,
                ]);
        }

        DB::table('expense_categories')
            ->whereNull('code')
            ->update(['is_active' => true]);

        DB::statement('CREATE UNIQUE INDEX expense_categories_organization_code_unique ON expense_categories (organization_id, code) WHERE code IS NOT NULL');

        DB::statement(<<<'SQL'
            UPDATE expenses AS e
            SET expense_category_id = c.id
            FROM expense_categories AS c
            WHERE e.organization_id = c.organization_id
              AND e.category = c.name
        SQL);

        DB::statement(<<<'SQL'
            UPDATE expenses AS e
            SET expense_account_id = a.id
            FROM accounts AS a
            WHERE e.organization_id = a.organization_id
              AND a.code = e.expense_account_code
              AND a.type = 'expense'
              AND a.is_active = true
        SQL);

        DB::statement(<<<'SQL'
            UPDATE recurring_expenses AS e
            SET expense_category_id = c.id
            FROM expense_categories AS c
            WHERE e.organization_id = c.organization_id
              AND e.category = c.name
        SQL);

        DB::statement(<<<'SQL'
            UPDATE recurring_expenses AS e
            SET expense_account_id = a.id
            FROM accounts AS a
            WHERE e.organization_id = a.organization_id
              AND a.code = e.expense_account_code
              AND a.type = 'expense'
              AND a.is_active = true
        SQL);
        //
    }

    /**
        DB::statement('DROP INDEX IF EXISTS expense_categories_organization_code_unique');

        Schema::table('recurring_expenses', function (Blueprint $table): void {
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['expense_account_id']);
            $table->dropIndex(['organization_id', 'expense_category_id']);
            $table->dropIndex(['organization_id', 'expense_account_id']);
            $table->dropColumn(['expense_category_id', 'expense_account_id']);
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['expense_account_id']);
            $table->dropIndex(['organization_id', 'expense_category_id']);
            $table->dropIndex(['organization_id', 'expense_account_id']);
            $table->dropColumn(['expense_category_id', 'expense_account_id']);
        });

        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->dropColumn(['code', 'translation_key', 'is_active', 'is_system', 'deactivated_at']);
        });
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
