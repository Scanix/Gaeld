<?php

use App\Domains\Payroll\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypt existing plaintext AHV numbers at rest.
 *
 * The Employee model's `ahv_number` field was previously stored as plain text.
 * This migration re-saves every employee record (including soft-deleted ones)
 * through the Eloquent model so the `encrypted` cast is applied, replacing
 * cleartext values with ciphertext encrypted by APP_KEY.
 *
 * No column type change is needed — the string column is wide enough to hold
 * the base64-encoded ciphertext produced by Laravel's Encrypter.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Temporarily widen the column to accommodate ciphertext (up to ~500 chars)
        Schema::table('employees', function (Blueprint $table) {
            $table->string('ahv_number', 600)->nullable()->change();
        });

        // Re-save each employee through the model to trigger the encrypted cast.
        // withoutGlobalScopes ensures org-scoping and soft-deletes do not exclude rows.
        Employee::withoutGlobalScopes()
            ->withTrashed()
            ->whereNotNull('ahv_number')
            ->each(function (Employee $employee) {
                // Only re-save if the value is not already encrypted.
                // Encrypted ciphertext always starts with 'eyJpd' (base64 of '{"iv"').
                if (! str_starts_with((string) $employee->getRawOriginal('ahv_number'), 'eyJpd')) {
                    $employee->save();
                }
            });
    }

    public function down(): void
    {
        // Intentionally no rollback — decrypting PII back to plaintext would
        // reintroduce the vulnerability this migration was designed to close.
    }
};
