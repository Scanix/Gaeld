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
class GetBalanceSheet extends Tool
{
    protected string $name = 'get_balance_sheet';

    protected string $description = 'Read the posted-ledger balance sheet in the organization base currency, including current fiscal year earnings. Provide as_of_date or fiscal_year_id.';

    public function shouldRegister(McpAccess $access): bool
    {
        return $access->canReadReports();
    }

    public function handle(Request $request, ReportReader $reader): ResponseFactory
    {
        return Response::structured($reader->balanceSheet($request));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'as_of_date' => $schema->string()->format('date'),
            'fiscal_year_id' => $schema->string()->format('uuid'),
        ];
    }
}
