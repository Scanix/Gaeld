<?php

namespace App\Domains\Api\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class ExternalReference
{
    /** @return array<string, list<string>> */
    public static function rules(Request $request): array
    {
        $required = $request->routeIs('api.*.upsert') || $request->hasAny(['external_source', 'external_id']);

        return [
            'external_source' => [$required ? 'required' : 'sometimes', 'string', 'max:100'],
            'external_id' => [$required ? 'required' : 'sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function filter(Builder $query, Request $request): void
    {
        $filters = $request->validate([
            'external_source' => ['sometimes', 'required', 'string', 'max:100'],
            'external_id' => ['sometimes', 'required', 'string', 'max:255'],
        ]);
        foreach ($filters as $field => $value) {
            $query->where($field, $value);
        }
    }
}
