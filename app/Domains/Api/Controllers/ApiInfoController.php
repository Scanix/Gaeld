<?php

namespace App\Domains\Api\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ApiInfoController extends Controller
{
    /**
     * API Information
     *
     * Returns the API name, version, documentation URL, and status.
     * This endpoint is unauthenticated and can be used as a health check.
     *
     * @group General
     *
     * @unauthenticated
     *
     * @response 200 {"name":"Gäld API","version":"v1","documentation":"https://docs.gaeld.ch/docs/api/gald-api-documentation","status":"ok"}
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name').' API',
            'version' => 'v1',
            'documentation' => 'https://docs.gaeld.ch/docs/api/gald-api-documentation',
            'status' => 'ok',
        ]);
    }
}
