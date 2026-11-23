<?php

use App\Domains\Api\Mcp\GaeldServer;
use App\Http\Middleware\EnsureMcpAccess;
use App\Http\Middleware\PrepareMcpRequest;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::middleware([
    PrepareMcpRequest::class,
    'auth:sanctum',
    EnsureMcpAccess::class,
    'feature:mcp_access',
    'feature:api_access',
    'throttle:mcp',
])->group(function (): void {
    Mcp::web('/mcp', GaeldServer::class)->name('mcp');
});
