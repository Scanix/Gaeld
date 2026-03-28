<?php

namespace App\Http\Controllers;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, CurrentOrganization $org, GlobalSearchService $searchService): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        return response()->json(['results' => $searchService->search($query, $org->id())]);
    }
}
