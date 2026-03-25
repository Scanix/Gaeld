<?php

namespace App\Console\Commands;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Console\Command;

class MarkOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark sent invoices past their due date as overdue';

    public function handle(): int
    {
        $count = Invoice::where('status', InvoiceStatus::Sent)
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => InvoiceStatus::Overdue]);

        $this->info("{$count} invoice(s) marked as overdue.");

        return self::SUCCESS;
    }
}
