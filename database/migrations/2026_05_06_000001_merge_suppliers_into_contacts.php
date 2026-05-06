<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merge the `suppliers` table into `customers`, then rename to `contacts`.
 *
 * Strategy:
 *   1. Add supplier-specific columns + role flags to `customers`.
 *   2. Drop the FK from expenses.supplier_id → suppliers.id.
 *   3. Copy every supplier row into customers (new IDs) and track the mapping.
 *   4. Update expenses.supplier_id to point at the new contact IDs.
 *   5. Update contact_persons polymorphic references.
 *   6. Rename `customers` → `contacts`.
 *   7. Add new FK expenses.supplier_id → contacts.id.
 *   8. Drop `suppliers`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Step 1: Add columns to customers ────────────────────────────────
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_customer')->default(true)->after('type');
            $table->boolean('is_supplier')->default(false)->after('is_customer');
            $table->string('iban')->nullable()->after('vat_number');
            $table->string('default_expense_category')->nullable()->after('iban');
        });

        // Mark all existing rows explicitly
        DB::table('customers')->update(['is_customer' => true, 'is_supplier' => false]);

        // ── Step 2: Drop old FK ──────────────────────────────────────────────
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        // ── Step 3: Migrate suppliers → customers ────────────────────────────
        /** @var array<int,int> $idMap old supplier_id => new customer_id */
        $idMap = [];

        DB::table('suppliers')->get()->each(function (object $supplier) use (&$idMap): void {
            $newId = DB::table('customers')->insertGetId([
                'uuid' => $supplier->uuid,
                'organization_id' => $supplier->organization_id,
                'type' => $supplier->type,
                'is_customer' => false,
                'is_supplier' => true,
                'name' => $supplier->name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'city' => $supplier->city,
                'postal_code' => $supplier->postal_code,
                'country' => $supplier->country,
                'vat_number' => $supplier->vat_number,
                'iban' => $supplier->iban,
                'default_expense_category' => $supplier->default_expense_category,
                'currency' => $supplier->currency,
                'payment_terms' => null,
                'notes' => $supplier->notes,
                'internal_notes' => $supplier->internal_notes,
                'deleted_at' => $supplier->deleted_at,
                'created_at' => $supplier->created_at,
                'updated_at' => $supplier->updated_at,
            ]);

            $idMap[$supplier->id] = $newId;
        });

        // ── Step 4: Remap expenses.supplier_id ──────────────────────────────
        foreach ($idMap as $oldSupplierId => $newContactId) {
            DB::table('expenses')
                ->where('supplier_id', $oldSupplierId)
                ->update(['supplier_id' => $newContactId]);
        }

        // ── Step 5: Update contact_persons morph references ─────────────────
        // Customers: just change the type string (IDs are unchanged)
        DB::table('contact_persons')
            ->where('contactable_type', 'App\Domains\Contacts\Models\Customer')
            ->update(['contactable_type' => 'App\Domains\Contacts\Models\Contact']);

        // Suppliers: change type string AND remap IDs
        foreach ($idMap as $oldSupplierId => $newContactId) {
            DB::table('contact_persons')
                ->where('contactable_type', 'App\Domains\Contacts\Models\Supplier')
                ->where('contactable_id', $oldSupplierId)
                ->update([
                    'contactable_type' => 'App\Domains\Contacts\Models\Contact',
                    'contactable_id' => $newContactId,
                ]);
        }

        // Any remaining supplier refs not matched above (orphans) — clean up
        DB::table('contact_persons')
            ->where('contactable_type', 'App\Domains\Contacts\Models\Supplier')
            ->update(['contactable_type' => 'App\Domains\Contacts\Models\Contact']);

        // ── Step 6: Rename customers → contacts ─────────────────────────────
        Schema::rename('customers', 'contacts');

        // ── Step 7: Add new FK expenses.supplier_id → contacts.id ───────────
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('contacts')
                ->nullOnDelete();
        });

        // ── Step 8: Drop suppliers ───────────────────────────────────────────
        Schema::dropIfExists('suppliers');
    }

    public function down(): void
    {
        // Recreate suppliers table
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->nullable(false);
            $table->uuid('organization_id');
            $table->string('type', 20)->default('organization');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 2)->default('CH');
            $table->string('vat_number')->nullable();
            $table->string('iban')->nullable();
            $table->string('default_expense_category')->nullable();
            $table->string('currency')->nullable();
            $table->json('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'name']);
        });

        // Rename contacts back to customers
        Schema::rename('contacts', 'customers');

        // Drop FK and restore to suppliers
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();
        });

        // Remove added columns from customers
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['is_customer', 'is_supplier', 'iban', 'default_expense_category']);
        });
    }
};
