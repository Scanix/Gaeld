<?php

namespace App\Domains\Api\Mcp\Tools;

use App\Domains\Api\Mcp\DocumentFilters;
use App\Domains\Api\Mcp\DocumentReader;
use App\Domains\Api\Mcp\McpAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
class ListInvoices extends Tool
{
    protected string $name = 'list_invoices';

    protected string $description = 'Search invoices and credit notes in the authorized organization. Returns one page, never global monetary totals.';

    public function shouldRegister(McpAccess $access): bool
    {
        return $access->canReadInvoices();
    }

    public function handle(Request $request, DocumentReader $reader, McpAccess $access): Response|ResponseFactory
    {
        if (! $access->canReadInvoices()) {
            return Response::error('Invoice access denied.');
        }

        return Response::structured($reader->invoices(DocumentFilters::validate($request, true)));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return DocumentFilters::schema($schema, true);
    }
}
