<?php

namespace App\Http\Services;

use App\Http\Contracts\SearchProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

abstract class BaseSearchProvider implements SearchProvider
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  string[]  $with
     */
    protected function searchModel(string $modelClass, string $query, string $orgId, int $limit, array $with = []): Collection
    {
        $usesScout = in_array(Searchable::class, class_uses_recursive($modelClass));

        if ($usesScout && config('scout.driver') === 'meilisearch') {
            $ids = $modelClass::search($query)
                ->where('organization_id', $orgId)
                ->keys()
                ->take($limit)
                ->all();

            if (empty($ids)) {
                return collect();
            }

            return $modelClass::whereIn('id', $ids)
                ->when($with, fn ($q) => $q->with($with))
                ->get();
        }

        $columns = $this->searchableColumns();
        $likeOp = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';

        return $modelClass::where('organization_id', $orgId)
            ->where(function ($q) use ($query, $columns, $likeOp) {
                foreach ($columns as $col) {
                    $q->orWhere($col, $likeOp, "%{$query}%");
                }
            })
            ->when($with, fn ($q) => $q->with($with))
            ->limit($limit)
            ->get();
    }

    /**
     * @return string[]
     */
    abstract protected function searchableColumns(): array;
}
