<?php

namespace App\Http\Contracts;

interface SearchProvider
{
    /**
     * @return array<array{type: string, id: string, title: string, subtitle: string, url: string, status?: string}>
     */
    public function search(string $query, string $orgId, int $limit): array;
}
