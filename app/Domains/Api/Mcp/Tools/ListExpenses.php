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
class ListExpenses extends Tool
{
    protected string $name = 'list_expenses';

    protected string $description = 'Search authorized expenses. Access may be limited to the user\'s own expenses. Returns one page, never global monetary totals.';

    public function shouldRegister(McpAccess $access): bool
    {
        return $access->canReadExpenses();
    }

    public function handle(Request $request, DocumentReader $reader, McpAccess $access): Response|ResponseFactory
    {
        if (! $access->canReadExpenses()) {
            return Response::error('Expense access denied.');
        }

        return Response::structured($reader->expenses(DocumentFilters::validate($request, false)));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return DocumentFilters::schema($schema, false);
    }
}
