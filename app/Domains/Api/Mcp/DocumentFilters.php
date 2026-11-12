<?php

namespace App\Domains\Api\Mcp;

use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;

class DocumentFilters
{
    /** @return array<string, Type> */
    public static function schema(JsonSchema $schema, bool $invoices): array
    {
        $fields = [
            'from' => $schema->string()->format('date')->description('Inclusive issue date for invoices, expense date for expenses.'),
            'to' => $schema->string()->format('date')->description('Inclusive end date.'),
            'status' => $schema->string()->enum($invoices ? InvoiceStatus::class : ExpenseStatus::class),
            'search' => $schema->string()->max(200)->description('Search invoice number or customer; expense description, vendor or category.'),
            'page' => $schema->integer()->min(1)->max(10000)->default(1),
            'per_page' => $schema->integer()->min(1)->max(50)->default(20),
            'direction' => $schema->string()->enum(['asc', 'desc'])->default('desc'),
        ];

        return $invoices ? [
            ...$fields,
            'type' => $schema->string()->enum(InvoiceType::class),
            'customer_id' => $schema->string()->format('uuid'),
            'due_before' => $schema->string()->format('date')->description('Inclusive due date cutoff.'),
            'sort' => $schema->string()->enum(['issue_date', 'due_date', 'total', 'number', 'status']),
        ] : [
            ...$fields,
            'supplier_id' => $schema->string()->format('uuid'),
            'category' => $schema->string()->max(255),
            'sort' => $schema->string()->enum(['date', 'amount', 'category', 'vendor', 'status']),
        ];
    }

    /** @return array<string, mixed> */
    public static function validate(Request $request, bool $invoices): array
    {
        $rules = [
            'from' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'required', Rule::enum($invoices ? InvoiceStatus::class : ExpenseStatus::class)],
            'search' => ['sometimes', 'required', 'string', 'max:200'],
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:50'],
            'direction' => ['sometimes', 'required', Rule::in(['asc', 'desc'])],
        ];

        if ($request->has('from')) {
            $rules['to'][] = 'after_or_equal:from';
        }

        $rules = $invoices ? [
            ...$rules,
            'type' => ['sometimes', 'required', Rule::enum(InvoiceType::class)],
            'customer_id' => ['sometimes', 'required', 'uuid'],
            'due_before' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'sort' => ['sometimes', 'required', Rule::in(['issue_date', 'due_date', 'total', 'number', 'status'])],
        ] : [
            ...$rules,
            'supplier_id' => ['sometimes', 'required', 'uuid'],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'sort' => ['sometimes', 'required', Rule::in(['date', 'amount', 'category', 'vendor', 'status'])],
        ];

        Validator::make(['arguments' => $request->all()], ['arguments' => 'array:'.implode(',', array_keys($rules))])->validate();

        return $request->validate($rules);
    }
}
