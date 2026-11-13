<?php

namespace App\Domains\Api\Mcp\Tools;

use App\Domains\Api\Mcp\DocumentReader;
use App\Domains\Api\Mcp\McpAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
class GetExpense extends Tool
{
    protected string $name = 'get_expense';

    protected string $description = 'Read an authorized expense by UUID, including its description and net, VAT and gross amounts. Does not download receipts.';

    public function shouldRegister(McpAccess $access): bool
    {
        return $access->canReadExpenses();
    }

    public function handle(Request $request, DocumentReader $reader, McpAccess $access): Response|ResponseFactory
    {
        if (! $access->canReadExpenses()) {
            return Response::error('Expense access denied.');
        }

        Validator::make(['arguments' => $request->all()], ['arguments' => 'array:id'])->validate();
        $validated = $request->validate(['id' => ['required', 'uuid']]);
        $expense = $reader->expense($validated['id']);

        return $expense === null ? Response::error('Document not found.') : Response::structured($expense);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->string()->format('uuid')->required()];
    }
}
