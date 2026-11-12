<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrepareMcpRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        if ($request->headers->has('Origin') && $request->header('Origin') !== $request->getSchemeAndHttpHost()) {
            abort(403, 'Origin is not allowed.');
        }

        if (strlen($request->getContent()) > 65536) {
            abort(413, 'MCP request is too large.');
        }

        return $next($request);
    }
}
