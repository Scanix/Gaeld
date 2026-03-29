<?php

namespace App\Http\Services;

use App\Http\Contracts\SearchProvider;

class GlobalSearchService
{
    private const PER_TYPE_LIMIT = 5;

    /** @var SearchProvider[] */
    private array $providers;

    public function __construct(SearchProvider ...$providers)
    {
        $this->providers = $providers;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function search(string $query, string $orgId): array
    {
        $results = [];

        foreach ($this->providers as $provider) {
            $results = array_merge($results, $provider->search($query, $orgId, self::PER_TYPE_LIMIT));
        }

        return $results;
    }
}
