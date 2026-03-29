<?php

namespace App\Domains\Invoicing\Search;

use App\Domains\Invoicing\Models\Invoice;
use App\Http\Services\BaseSearchProvider;

class InvoiceSearchProvider extends BaseSearchProvider
{
    public function search(string $query, string $orgId, int $limit): array
    {
        $results = [];

        foreach ($this->searchModel(Invoice::class, $query, $orgId, $limit, ['customer']) as $invoice) {
            $results[] = [
                'type' => 'invoice',
                'id' => $invoice->id,
                'title' => $invoice->number ?? __('app.draft'),
                'subtitle' => ($invoice->customer?->name ?? '').' · '.$invoice->currency.' '.number_format((float) $invoice->total, 2),
                'status' => $invoice->status?->value,
                'url' => route('invoices.show', $invoice),
            ];
        }

        return $results;
    }

    protected function searchableColumns(): array
    {
        return ['number', 'notes'];
    }
}
