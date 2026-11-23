<?php

namespace App\Domains\Api\Mcp\Tools;

use App\Domains\Api\Mcp\McpAccess;
use App\Domains\Api\Mcp\ReportReader;
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
class GetAgingReport extends Tool
{
    protected string $name = 'get_aging_report';

    protected string $description = 'Read current receivables or payables by aging bracket in one currency. Totals cover all pages. This is an operational report, not a historical reconstruction.';

    public function shouldRegister(McpAccess $access): bool
    {
        return $access->canReadAging('receivables') || $access->canReadAging('payables');
    }

    public function handle(Request $request, ReportReader $reader): ResponseFactory
    {
        return Response::structured($reader->aging($request));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['receivables', 'payables'])->required(),
            'as_of_date' => $schema->string()->format('date')->required(),
            'currency' => $schema->string()->description('ISO currency code; defaults to organization base currency.'),
            'page' => $schema->integer()->min(1)->max(10000)->default(1),
            'per_page' => $schema->integer()->min(1)->max(50)->default(20),
        ];
    }
}
