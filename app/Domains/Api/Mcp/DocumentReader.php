<?php

namespace App\Domains\Api\Mcp;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Queries\ExpenseQuery;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Queries\InvoiceQuery;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentReader
{
    public function __construct(
        private CurrentOrganization $organization,
        private McpAccess $access,
        private Request $request,
    ) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function invoices(array $filters): array
    {
        Gate::authorize('viewAny', Invoice::class);

        $invoices = InvoiceQuery::filtered($this->filterRequest($filters))
            ->withOnly('customer')
            ->where('organization_id', $this->organization->id())
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);

        return $this->page($invoices, $filters, fn (Invoice $invoice): array => $this->invoiceData($invoice));
    }

    /** @return array<string, mixed>|null */
    public function invoice(string $id): ?array
    {
        $invoice = Invoice::query()->where('organization_id', $this->organization->id())
            ->with(['customer', 'lines.vatRate', 'payments'])->withSum('payments', 'amount')->find($id);

        if (! $invoice) {
            return null;
        }

        Gate::authorize('view', $invoice);

        return $this->envelope(['data' => [
            ...$this->invoiceData($invoice),
            'notes' => $invoice->notes,
            'lines' => $invoice->lines->map(fn ($line): array => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'amount' => (string) $line->amount,
                'vat_amount' => (string) $line->vat_amount,
            ])->all(),
            'payments' => $invoice->payments->map(fn ($payment): array => [
                'id' => $payment->id,
                'amount' => (string) $payment->amount,
                'date' => $payment->payment_date->toDateString(),
                'method' => $payment->payment_method->value,
            ])->all(),
        ]]);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function expenses(array $filters): array
    {
        Gate::authorize('viewAny', Expense::class);

        $query = ExpenseQuery::filtered($this->filterRequest($filters));
        $this->scopeExpenses($query);
        $expenses = $query->orderBy('id')->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);

        return $this->page($expenses, $filters, fn (Expense $expense): array => $this->expenseData($expense));
    }

    /** @return array<string, mixed>|null */
    public function expense(string $id): ?array
    {
        $query = Expense::query();
        $this->scopeExpenses($query);
        $expense = $query->find($id);

        if (! $expense) {
            return null;
        }

        Gate::authorize('view', $expense);

        return $this->envelope(['data' => [...$this->expenseData($expense), 'description' => $expense->description]]);
    }

    /** @param Builder<Expense> $query */
    private function scopeExpenses(Builder $query): void
    {
        $query->where('organization_id', $this->organization->id());

        if (! $this->access->canReadAllExpenses()) {
            $query->where('user_id', $this->request->user()->id);
        }
    }

    /** @param array<string, mixed> $filters */
    private function filterRequest(array $filters): Request
    {
        $request = Request::create('/', 'GET', [
            ...$filters,
            'filter' => array_intersect_key($filters, array_flip(['status', 'type', 'category'])),
        ]);
        $request->setUserResolver($this->request->getUserResolver());

        return $request;
    }

    /** @return array<string, mixed> */
    private function invoiceData(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'type' => $invoice->type->value,
            'customer' => $invoice->customer ? ['id' => $invoice->customer->uuid, 'name' => $invoice->customer->name] : null,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'subtotal' => (string) $invoice->subtotal,
            'vat_amount' => (string) $invoice->vat_amount,
            'total' => (string) $invoice->total,
            'amount_paid' => Money::add($invoice->amountPaid(), '0'),
            'amount_due' => $invoice->amountDue(),
            'currency' => $invoice->currency,
            'updated_at' => $invoice->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function expenseData(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'date' => $expense->date->toDateString(),
            'vendor' => $expense->vendor,
            'category' => $expense->category,
            'status' => $expense->status->value,
            'net_amount' => (string) $expense->amount,
            'vat_amount' => (string) $expense->vat_amount,
            'gross_amount' => $expense->gross_amount,
            'currency' => $expense->currency,
            'updated_at' => $expense->updated_at?->toIso8601String(),
        ];
    }

    /** @template TDocument of Invoice|Expense
     * @param  LengthAwarePaginator<int, TDocument>  $page
     * @param  callable(TDocument): array<string, mixed>  $serialize
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function page(LengthAwarePaginator $page, array $filters, callable $serialize): array
    {
        return $this->envelope([
            'filters' => (object) $filters,
            'data' => array_map($serialize, $page->items()),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'has_more' => $page->hasMorePages(),
            ],
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function envelope(array $data): array
    {
        return [
            'organization' => ['id' => $this->organization->id(), 'name' => $this->organization->get()->name],
            'generated_at' => now()->toIso8601String(),
            ...$data,
        ];
    }
}
