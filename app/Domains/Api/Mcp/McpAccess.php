<?php

namespace App\Domains\Api\Mcp;

use App\Domains\Api\Models\PersonalAccessToken;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Auth;

class McpAccess
{
    /** @var array<int, string> */
    public const READ_ABILITIES = ['invoicing.view', 'expenses.view', 'expenses.view-own', 'accounting.view', 'reporting.view'];

    public function canReadReports(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && self::acceptsToken($user->currentAccessToken())
            && $user->tokenCan(Permission::ReportingView->value)
            && $user->hasAllPermissions([Permission::AccountingView, Permission::ReportingView]);
    }

    public function canReadAging(string $type): bool
    {
        return $this->canReadReports() && match ($type) {
            'receivables' => $this->canReadInvoices(),
            'payables' => $this->canReadAllExpenses(),
            default => false,
        };
    }

    public static function acceptsToken(mixed $token): bool
    {
        return $token instanceof PersonalAccessToken
            && $token->isPersonal()
            && $token->organization_id !== null
            && is_array($token->abilities)
            && $token->abilities !== []
            && array_diff($token->abilities, self::READ_ABILITIES) === [];
    }

    public function canReadInvoices(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && self::acceptsToken($user->currentAccessToken())
            && $user->tokenCan(Permission::InvoicingView->value)
            && $user->hasPermissionTo(Permission::InvoicingView);
    }

    public function canReadExpenses(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && self::acceptsToken($user->currentAccessToken())
            && ($user->tokenCan(Permission::ExpensesView->value) || $user->tokenCan(Permission::ExpensesViewOwn->value))
            && $user->hasAnyPermission([Permission::ExpensesView, Permission::ExpensesViewOwn]);
    }

    public function canReadAllExpenses(): bool
    {
        $user = Auth::user();

        return $this->canReadExpenses()
            && $user->tokenCan(Permission::ExpensesView->value)
            && $user->hasPermissionTo(Permission::ExpensesView);
    }
}
