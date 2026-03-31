<?php

namespace App\Http\Controllers;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cross-domain full-text search endpoint (Meilisearch-backed).
 */
class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, CurrentOrganization $org, GlobalSearchService $searchService): JsonResponse|Response
    {
        $query = trim($request->input('q', ''));

        if (! $request->wantsJson() && ! $request->header('X-Requested-With')) {
            $results = mb_strlen($query) >= 2
                ? $searchService->search($query, $org->id())
                : [];

            return Inertia::render('Search/Index', [
                'query' => $query,
                'results' => $results,
            ]);
        }

        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        return response()->json(['results' => $searchService->search($query, $org->id())]);
    }
}
