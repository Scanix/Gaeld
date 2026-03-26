<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\Invoice;

class InvoiceNumberGenerator
{
    /**
     * Generate the next invoice number for a given organization.
     *
     * Format: {PREFIX}-YYYY-NNN (e.g. INV-2026-001)
     */
    public function next(string $organizationId): string
    {
        $year = now()->year;
        $configPrefix = config('accounting.invoice_number_prefix', 'INV');
        $prefix = "{$configPrefix}-{$year}-";
        $prefixLen = strlen($prefix);

        $maxSequence = Invoice::where('organization_id', $organizationId)
            ->where('number', 'like', "{$prefix}%")
            ->withTrashed()
            ->pluck('number')
            ->map(fn (string $number) => (int) substr($number, $prefixLen))
            ->max() ?? 0;

        $next = $maxSequence + 1;

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
