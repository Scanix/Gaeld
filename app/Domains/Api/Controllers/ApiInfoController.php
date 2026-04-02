<?php

namespace App\Domains\Api\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ApiInfoController extends Controller
{
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
