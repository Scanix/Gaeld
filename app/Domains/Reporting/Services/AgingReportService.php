<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Generates receivables (unpaid invoices) or payables (pending/approved expenses)
 * aging reports, grouped into standard age brackets.
 */
class AgingReportService
{
    /**
     * Generate an aging report.
     *
     * @param  string  $orgId  Organization UUID
     * @param  string  $type  'receivables' or 'payables'
     * @param  string|null  $asOfDate  Cut-off date (defaults to today)
     */
    public function generate(string $orgId, string $type, ?string $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate) : Carbon::now();
        $asOfString = $asOf->toDateString();

        $cacheKey = "aging:{$orgId}:{$type}:{$asOfString}";
        $orgTag = "org:{$orgId}:reports";

        return Cache::tags([$orgTag])->remember($cacheKey, now()->addMinutes(30), function () use ($orgId, $type, $asOf, $asOfString) {
            $items = $type === 'receivables'
                ? $this->receivableItems($orgId, $asOf)
                : $this->payableItems($orgId, $asOf);

            $brackets = [
                'current' => ['items' => [], 'total' => '0.00'],
                '1_30' => ['items' => [], 'total' => '0.00'],
                '31_60' => ['items' => [], 'total' => '0.00'],
                '61_90' => ['items' => [], 'total' => '0.00'],
                '90_plus' => ['items' => [], 'total' => '0.00'],
            ];

            foreach ($items as $item) {
                $key = $this->bracket($item['days_overdue']);
                $brackets[$key]['items'][] = $item;
                $brackets[$key]['total'] = bcadd($brackets[$key]['total'], (string) $item['amount'], 2);
            }

            $grandTotal = array_reduce($brackets, fn ($carry, $b) => bcadd($carry, $b['total'], 2), '0.00');

            return [
                'type' => $type,
                'as_of_date' => $asOfString,
                'brackets' => $brackets,
                'grand_total' => $grandTotal,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────
    //  Data Sources
    // ──────────────────────────────────────────────────────────────

    private function receivableItems(string $orgId, Carbon $asOf): array
    {
        $invoices = Invoice::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->where('due_date', '<=', $asOf->toDateString())
            ->with('customer')
            ->get();

        // Also include invoices not yet due (current bucket — due_date > asOf)
        $notYetDue = Invoice::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('status', InvoiceStatus::Sent->value)
            ->where('due_date', '>', $asOf->toDateString())
            ->with('customer')
            ->get();

        return $invoices->merge($notYetDue)
            ->map(function (Invoice $invoice) use ($asOf) {
                $dueDate = $invoice->due_date;
                $daysOverdue = $dueDate->isBefore($asOf->startOfDay())
                    ? (int) $dueDate->diffInDays($asOf)
                    : 0;

                /** @var Customer|null $customer */
                $customer = $invoice->customer;

                return [
                    'document_number' => $invoice->number ?? $invoice->id,
                    'name' => $customer ? $customer->name : '-',
                    'date' => $invoice->issue_date->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'amount' => (string) $invoice->total,
                    'days_overdue' => $daysOverdue,
                ];
            })
            ->all();
    }

    private function payableItems(string $orgId, Carbon $asOf): array
    {
        $expenses = Expense::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereIn('status', [ExpenseStatus::Pending->value, ExpenseStatus::Approved->value])
            ->where('date', '<=', $asOf->toDateString())
            ->with('supplier')
            ->get();

        return $expenses->map(function (Expense $expense) use ($asOf) {
            $expenseDate = $expense->date;
            $daysOverdue = $expenseDate->isBefore($asOf->startOfDay())
                ? (int) $expenseDate->diffInDays($asOf)
                : 0;

            /** @var Supplier|null $supplier */
            $supplier = $expense->supplier;

            return [
                'document_number' => $expense->id,
                'name' => $supplier ? $supplier->name : ($expense->vendor ?? '-'),
                'date' => $expenseDate->toDateString(),
                'due_date' => $expenseDate->toDateString(),
                'amount' => (string) $expense->amount,
                'days_overdue' => $daysOverdue,
            ];
        })->all();
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    private function bracket(int $daysOverdue): string
    {
        if ($daysOverdue === 0) {
            return 'current';
        }
        if ($daysOverdue <= 30) {
            return '1_30';
        }
        if ($daysOverdue <= 60) {
            return '31_60';
        }
        if ($daysOverdue <= 90) {
            return '61_90';
        }

        return '90_plus';
    }
}
