<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Application health-check endpoint for load balancers and monitoring.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];

        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            Log::error('Health check: database connection failed', ['exception' => $e->getMessage()]);
            $checks['database'] = 'error';
        }

        try {
            Cache::store()->set('health_check', true, 10);
            $checks['cache'] = Cache::store()->get('health_check') ? 'ok' : 'error';
        } catch (\Throwable $e) {
            Log::error('Health check: cache connection failed', ['exception' => $e->getMessage()]);
            $checks['cache'] = 'error';
        }

        $healthy = ! in_array('error', $checks, true);

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
