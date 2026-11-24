<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Enums\RequestHeader;
use Laravel\Mcp\Server\Middleware\ValidateMcpHeaders;
use Symfony\Component\HttpFoundation\Response;

class ValidateGaeldMcpHeaders extends ValidateMcpHeaders
{
    protected function unsupportedLegacyVersion(Request $request, mixed $id): ?Response
    {
        if ($request->routeIs('mcp') && $request->header(RequestHeader::PROTOCOL_VERSION->value) === ProtocolVersion::V2024_11_05->value) {
            return null;
        }

        return parent::unsupportedLegacyVersion($request, $id);
    }
}
