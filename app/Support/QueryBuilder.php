<?php

namespace App\Support;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Scout\Searchable;

/**
 * Shared infrastructure for building filterable, sortable, and searchable
 * Eloquent queries from HTTP request parameters.
 *
 * Lives in App\Support (not a Domain) because it is a generic query utility
 * with no domain-specific logic — it is consumed across all domains.
 */
class QueryBuilder
{
    private Builder $query;
    private Request $request;
    private array $allowedSorts = [];
    private array $allowedFilters = [];
    private array $searchColumns = [];
    private string $defaultSort = 'created_at';
    private string $defaultDirection = 'desc';

    public function __construct(Builder $query, Request $request)
    {
        $this->query = $query;
        $this->request = $request;
    }

    public static function for(Builder $query, Request $request): static
    {
        return new static($query, $request);
    }

    /**
     * Define which columns can be sorted by.
     *
     * @param array<string> $columns
     */
    public function allowedSorts(array $columns, string $default = 'created_at', string $defaultDirection = 'desc'): static
    {
        $this->allowedSorts = $columns;
        $this->defaultSort = $default;
        $this->defaultDirection = $defaultDirection;

        return $this;
    }

    /**
     * Define which columns can be filtered (exact match).
     *
     * @param array<string> $columns
     */
    public function allowedFilters(array $columns): static
    {
        $this->allowedFilters = $columns;

        return $this;
    }

    /**
     * Define which columns are searched via LIKE.
     *
     * @param array<string> $columns
     */
    public function searchable(array $columns): static
    {
        $this->searchColumns = $columns;

        return $this;
    }

    /**
     * Apply sorting, filtering, search from request and return the builder.
     */
    public function apply(): Builder
    {
        $this->applyFilters();
        $this->applySearch();
        $this->applySorting();

        return $this->query;
    }

    private function applyFilters(): void
    {
        foreach ($this->allowedFilters as $filter) {
            $value = $this->request->input("filter.$filter");

            if ($value !== null && $value !== '') {
                $this->query->where($filter, $value);
            }
        }
    }

    private function applySearch(): void
    {
        $search = $this->request->input('search');

        if (empty($search) || empty($this->searchColumns)) {
            return;
        }

        $search = trim($search);
        $model = $this->query->getModel();

        // Use MeiliSearch when available and the model is searchable
        if (config('scout.driver') === 'meilisearch' && in_array(Searchable::class, class_uses_recursive($model))) {
            $this->applyMeiliSearch($search, $model);

            return;
        }

        $this->applyDatabaseSearch($search);
    }

    private function applyMeiliSearch(string $search, $model): void
    {
        $scoutQuery = $model::search($search);

        // Tenant isolation: filter by current organization
        $currentOrg = app(CurrentOrganization::class);
        if ($currentOrg->isBound()) {
            $scoutQuery->where('organization_id', $currentOrg->id());
        }

        $ids = $scoutQuery->keys()->all();

        if (empty($ids)) {
            // No results — force empty result set
            $this->query->whereRaw('1 = 0');

            return;
        }

        $table = $model->getTable();
        $this->query->whereIn("{$table}.id", $ids);
    }

    private function applyDatabaseSearch(string $search): void
    {
        $likeOperator = $this->likeOperator();

        $this->query->where(function (Builder $q) use ($search, $likeOperator) {
            foreach ($this->searchColumns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $field] = explode('.', $column, 2);
                    $q->orWhereHas($relation, function (Builder $rq) use ($field, $search, $likeOperator) {
                        $rq->where($field, $likeOperator, "%{$search}%");
                    });
                } else {
                    $q->orWhere($column, $likeOperator, "%{$search}%");
                }
            }
        });
    }

    private function applySorting(): void
    {
        $sort = $this->request->input('sort', $this->defaultSort);
        $direction = $this->request->input('direction', $this->defaultDirection);

        // Validate sort column against allowed list; reset direction to default if column is rejected
        if (! in_array($sort, $this->allowedSorts, true)) {
            $sort = $this->defaultSort;
            $direction = $this->defaultDirection;
        }

        // Validate direction
        $direction = in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction)
            : $this->defaultDirection;

        $this->query->orderBy($sort, $direction);
    }

    /**
     * Use case-insensitive LIKE: ilike for PostgreSQL, like for others.
     */
    private function likeOperator(): string
    {
        return $this->query->getQuery()->getConnection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';
    }
}
