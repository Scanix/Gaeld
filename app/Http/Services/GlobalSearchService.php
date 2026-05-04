<?php

namespace App\Http\Services;

use App\Http\Contracts\SearchProvider;
use Illuminate\Support\Facades\Log;

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
            try {
                $results = array_merge($results, $provider->search($query, $orgId, self::PER_TYPE_LIMIT));
            } catch (\Throwable $e) {
                Log::warning('Search provider failed', [
                    'provider' => get_class($provider),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
