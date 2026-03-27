<?php

namespace App\Http\Controllers;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

class GlobalSearchController extends Controller
{
    private const PER_TYPE_LIMIT = 5;

    public function __invoke(Request $request, CurrentOrganization $org): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $orgId = $org->id();

        $results = [];

        // Search invoices
        $invoices = $this->searchModel(Invoice::class, $query, $orgId, ['customer']);
        foreach ($invoices as $invoice) {
            $results[] = [
                'type' => 'invoice',
                'id' => $invoice->id,
                'title' => $invoice->number ?? __('app.draft'),
                'subtitle' => ($invoice->customer?->name ?? '').' · '.$invoice->currency.' '.number_format((float) $invoice->total, 2),
                'status' => $invoice->status?->value,
                'url' => "/invoices/{$invoice->id}",
            ];
        }

        // Search customers
        $customers = $this->searchModel(Customer::class, $query, $orgId);
        foreach ($customers as $customer) {
            $results[] = [
                'type' => 'customer',
                'id' => $customer->id,
                'title' => $customer->name,
                'subtitle' => collect([$customer->email, $customer->city])->filter()->implode(' · '),
                'url' => "/customers/{$customer->id}",
            ];
        }

        // Search suppliers
        $suppliers = $this->searchModel(Supplier::class, $query, $orgId);
        foreach ($suppliers as $supplier) {
            $results[] = [
                'type' => 'supplier',
                'id' => $supplier->id,
                'title' => $supplier->name,
                'subtitle' => collect([$supplier->email, $supplier->city])->filter()->implode(' · '),
                'url' => "/suppliers/{$supplier->id}",
            ];
        }

        // Search expenses
        $expenses = $this->searchModel(Expense::class, $query, $orgId, ['supplier']);
        foreach ($expenses as $expense) {
            $results[] = [
                'type' => 'expense',
                'id' => $expense->id,
                'title' => $expense->description ?? $expense->category,
                'subtitle' => ($expense->vendor ?? $expense->supplier?->name ?? '').' · '.$expense->currency.' '.number_format((float) $expense->amount, 2),
                'status' => $expense->status?->value,
                'url' => "/expenses/{$expense->id}",
            ];
        }

        return response()->json(['results' => $results]);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  string[]  $with
     */
    private function searchModel(string $modelClass, string $query, string $orgId, array $with = []): Collection
    {
        $usesScout = in_array(Searchable::class, class_uses_recursive($modelClass));

        if ($usesScout && config('scout.driver') === 'meilisearch') {
            $ids = $modelClass::search($query)
                ->where('organization_id', $orgId)
                ->keys()
                ->take(self::PER_TYPE_LIMIT)
                ->all();

            if (empty($ids)) {
                return collect();
            }

            return $modelClass::whereIn('id', $ids)
                ->when($with, fn ($q) => $q->with($with))
                ->get();
        }

        // Database fallback: LIKE search on key columns
        $columns = $this->searchableColumns($modelClass);
        $likeOp = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';

        return $modelClass::where('organization_id', $orgId)
            ->where(function ($q) use ($query, $columns, $likeOp) {
                foreach ($columns as $col) {
                    $q->orWhere($col, $likeOp, "%{$query}%");
                }
            })
            ->when($with, fn ($q) => $q->with($with))
            ->limit(self::PER_TYPE_LIMIT)
            ->get();
    }

    private function searchableColumns(string $modelClass): array
    {
        return match ($modelClass) {
            Invoice::class => ['number', 'notes'],
            Customer::class => ['name', 'email', 'city', 'vat_number'],
            Supplier::class => ['name', 'email', 'city', 'vat_number'],
            Expense::class => ['description', 'vendor', 'category'],
            default => ['name'],
        };
    }
}
