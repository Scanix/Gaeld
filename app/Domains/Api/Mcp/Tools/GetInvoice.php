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
class GetInvoice extends Tool
{
    protected string $name = 'get_invoice';

    protected string $description = 'Read an invoice or credit note by UUID, including lines, payments and outstanding balance. Does not download files.';

    public function shouldRegister(McpAccess $access): bool
    {
        return $access->canReadInvoices();
    }

    public function handle(Request $request, DocumentReader $reader, McpAccess $access): Response|ResponseFactory
    {
        if (! $access->canReadInvoices()) {
            return Response::error('Invoice access denied.');
        }

        Validator::make(['arguments' => $request->all()], ['arguments' => 'array:id'])->validate();
        $validated = $request->validate(['id' => ['required', 'uuid']]);
        $invoice = $reader->invoice($validated['id']);

        return $invoice === null ? Response::error('Document not found.') : Response::structured($invoice);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->string()->format('uuid')->required()];
    }
}
