<?php

namespace App\Domains\Expenses\Search;

use App\Domains\Expenses\Models\Expense;
use App\Http\Services\BaseSearchProvider;

class ExpenseSearchProvider extends BaseSearchProvider
{
    public function search(string $query, string $orgId, int $limit): array
    {
        $results = [];

        foreach ($this->searchModel(Expense::class, $query, $orgId, $limit, ['supplier']) as $expense) {
            $results[] = [
                'type' => 'expense',
                'id' => $expense->id,
                'title' => $expense->description ?? $expense->category,
                'subtitle' => ($expense->vendor ?? $expense->supplier?->name ?? '').' · '.$expense->currency.' '.number_format((float) $expense->amount, 2),
                'status' => $expense->status?->value,
                'url' => route('expenses.show', $expense),
            ];
        }

        return $results;
    }

    protected function searchableColumns(): array
    {
        return ['description', 'vendor', 'category'];
    }
}
