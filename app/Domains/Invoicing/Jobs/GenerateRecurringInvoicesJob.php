<?php

namespace App\Domains\Invoicing\Jobs;

use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\Models\RecurringInvoice;
use App\Domains\Invoicing\Services\InvoiceNumberGenerator;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateRecurringInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(CreateInvoiceAction $createInvoice, InvoiceNumberGenerator $numberGenerator): void
    {
        $dueRecurrings = RecurringInvoice::withoutGlobalScope('organization')
            ->active()
            ->due(Carbon::today())
            ->get();

        foreach ($dueRecurrings as $recurring) {
            try {
                $this->generateInvoice($recurring, $createInvoice, $numberGenerator);
                $this->advanceSchedule($recurring);
            } catch (\Throwable $e) {
                Log::error('GenerateRecurringInvoicesJob: failed to generate', [
                    'recurring_invoice_id' => $recurring->id,
                    'organization_id' => $recurring->organization_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function generateInvoice(
        RecurringInvoice $recurring,
        CreateInvoiceAction $createInvoice,
        InvoiceNumberGenerator $numberGenerator,
    ): void {
        $template = $recurring->template_data;
        $issueDate = $recurring->next_issue_date->toDateString();
        $dueDate = $recurring->next_issue_date->copy()->addDays(30)->toDateString();

        $lines = array_map(fn (array $line) => [
            'description' => $line['description'],
            'quantity' => (string) $line['quantity'],
            'unit_price' => (string) $line['unit_price'],
            'vat_rate_id' => $line['vat_rate_id'] ?? null,
            'sort_order' => $line['sort_order'] ?? 0,
        ], $template['lines'] ?? []);

        $invoiceData = CreateInvoiceData::fromArray([
            'organization_id' => $recurring->organization_id,
            'customer_id' => (string) $recurring->customer_id,
            'number' => $numberGenerator->next($recurring->organization_id),
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'currency' => $template['currency'] ?? 'CHF',
            'notes' => $template['notes'] ?? null,
            'payment_terms' => $template['payment_terms'] ?? null,
            'lines' => $lines,
        ]);

        $createInvoice->execute($invoiceData);

        Log::info('GenerateRecurringInvoicesJob: invoice created', [
            'recurring_invoice_id' => $recurring->id,
            'organization_id' => $recurring->organization_id,
        ]);
    }

    private function advanceSchedule(RecurringInvoice $recurring): void
    {
        $nextDate = $recurring->frequency->nextDate($recurring->next_issue_date);

        if ($recurring->end_date && $nextDate->greaterThan($recurring->end_date)) {
            $recurring->update([
                'is_active' => false,
                'next_issue_date' => $nextDate,
            ]);
        } else {
            $recurring->update([
                'next_issue_date' => $nextDate,
            ]);
        }
    }
}
