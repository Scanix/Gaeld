<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\Invoice;

/**
 * Generates sequential invoice numbers scoped to an organization and year.
 *
 * Default format: {PREFIX}-YYYY-NNN (e.g. INV-2026-001, CN-2026-001).
 */
class InvoiceNumberGenerator
{
    /**
     * Generate the next invoice number for a given organization.
     *
     * Format: {PREFIX}-YYYY-NNN (e.g. INV-2026-001, CN-2026-001)
     *
     * Pass $forYear to pin the year (e.g. for a recurring invoice scheduled in a past year
     * that is generated today). Defaults to the current year when null.
     */
    public function next(string $organizationId, ?string $prefix = null, ?int $forYear = null): string
    {
        $year = $forYear ?? now()->year;
        $configPrefix = $prefix ?? config('accounting.invoice_number_prefix', 'INV');
        $fullPrefix = "{$configPrefix}-{$year}-";
        $prefixLen = strlen($fullPrefix);

        $maxSequence = Invoice::where('organization_id', $organizationId)
            ->where('number', 'like', "{$fullPrefix}%")
            ->withTrashed()
            ->pluck('number')
            ->map(fn (string $number) => (int) substr($number, $prefixLen))
            ->max() ?? 0;

        $next = $maxSequence + 1;

        return $fullPrefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
