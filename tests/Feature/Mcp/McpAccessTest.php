<?php

namespace Tests\Feature\Mcp;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Api\Mcp\McpAccess;
use App\Domains\Api\Models\PersonalAccessToken;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Enums\Permission;
use App\Support\Contracts\FeatureResolver;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;

class McpAccessTest extends McpTestCase
{
    public function test_ce_initializes_and_exposes_only_four_read_only_tools_without_ee(): void
    {
        $this->assertFalse((bool) config('plugins.enabled'));
        $token = $this->mcpToken();
        $this->rpc($token, 'initialize', [
            'protocolVersion' => '2025-06-18', 'capabilities' => (object) [],
            'clientInfo' => ['name' => 'Gäld test', 'version' => '1.0'],
        ])->assertOk()->assertJsonPath('result.serverInfo.name', 'Gäld');
        $tools = $this->rpc($token, 'tools/list')->assertOk()->json('result.tools');
        $this->assertEqualsCanonicalizing(['list_invoices', 'get_invoice', 'list_expenses', 'get_expense'], array_column($tools, 'name'));
        foreach ($tools as $tool) {
            $this->assertTrue($tool['annotations']['readOnlyHint']);
            $this->assertFalse($tool['annotations']['openWorldHint']);
        }
    }

    public function test_missing_and_invalid_tokens_return_json_without_a_login_redirect(): void
    {
        $this->post('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
        $this->rpc('invalid-token', 'tools/list')->assertUnauthorized();
    }

    #[DataProvider('unsafeAbilities')]
    public function test_wildcard_empty_and_write_tokens_are_rejected(array $abilities): void
    {
        $this->rpc($this->mcpToken($abilities), 'tools/list')->assertForbidden();
    }

    public static function unsafeAbilities(): array
    {
        return [[['*']], [[]], [['invoicing.view', 'invoicing.create']], [['expenses.approve']]];
    }

    public function test_organization_tokens_and_session_authentication_are_rejected(): void
    {
        $token = $this->mcpToken();
        PersonalAccessToken::query()->latest('id')->firstOrFail()->update(['type' => TokenType::Organization]);
        $this->rpc($token, 'tools/list')->assertForbidden();
        Auth::forgetGuards();
        $this->actingAs($this->ownerA)->withHeader('Authorization', '')->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
        ])->assertForbidden();
    }

    public function test_revocation_and_expiration_take_effect_on_the_next_call(): void
    {
        $token = $this->mcpToken();
        $record = PersonalAccessToken::query()->latest('id')->firstOrFail();
        $this->rpc($token, 'tools/list')->assertOk();
        $record->update(['expires_at' => now()->subMinute()]);
        $this->rpc($token, 'tools/list')->assertUnauthorized();
        $record->update(['expires_at' => now()->addDay()]);
        $this->rpc($token, 'tools/list')->assertOk();
        $record->delete();
        $this->rpc($token, 'tools/list')->assertUnauthorized();
    }

    public function test_removed_members_and_tokens_without_an_organization_are_rejected(): void
    {
        $token = $this->mcpToken();
        $this->orgA->users()->detach($this->ownerA->id);
        $this->rpc($token, 'tools/list')->assertForbidden();
        $this->assertFalse(McpAccess::acceptsToken(new PersonalAccessToken([
            'type' => TokenType::Personal, 'abilities' => ['invoicing.view'],
        ])));
    }

    public function test_token_scopes_hide_and_prevent_calls_to_unauthorized_tools(): void
    {
        $token = $this->mcpToken(['expenses.view']);
        $tools = $this->rpc($token, 'tools/list')->json('result.tools');
        $this->assertEqualsCanonicalizing(['list_expenses', 'get_expense'], array_column($tools, 'name'));
        $this->callTool($token, 'list_invoices')->assertJsonPath('error.code', -32602);
    }

    public function test_user_permissions_are_checked_even_when_the_token_allows_reading(): void
    {
        $employee = $this->createUserInOrg($this->orgA, 'employee');
        $token = $this->mcpToken(['invoicing.view'], $employee);
        $this->rpc($token, 'tools/list')->assertJsonCount(0, 'result.tools');
        $this->callTool($token, 'list_invoices')->assertJsonPath('error.code', -32602);
    }

    public function test_removed_permissions_are_effective_on_the_next_call(): void
    {
        $user = $this->createUserInOrg($this->orgA, 'employee');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->orgA->id);
        $user->givePermissionTo(Permission::InvoicingView);
        $token = $this->mcpToken(['invoicing.view'], $user);
        $this->rpc($token, 'tools/list')->assertJsonCount(2, 'result.tools');
        $user->revokePermissionTo(Permission::InvoicingView);
        $this->rpc($token, 'tools/list')->assertJsonCount(0, 'result.tools');
    }

    public function test_token_organization_overrides_session_and_switching_cannot_leak_data(): void
    {
        $invoiceA = Invoice::factory()->create(['organization_id' => $this->orgA->id]);
        $invoiceB = Invoice::factory()->create(['organization_id' => $this->orgB->id]);
        $tokenA = $this->mcpToken(['invoicing.view']);
        $tokenB = $this->mcpToken(['invoicing.view'], $this->ownerB, $this->orgB);
        foreach ([$tokenA => $invoiceA, $tokenB => $invoiceB] as $token => $invoice) {
            $this->withSession(['current_organization_id' => $this->orgB->id]);
            $this->callTool($token, 'list_invoices')->assertJsonCount(1, 'result.structuredContent.data')
                ->assertJsonPath('result.structuredContent.data.0.id', $invoice->id);
        }
    }

    public function test_disabled_installation_flags_deny_access(): void
    {
        $token = $this->mcpToken();
        config(['features.mcp_access' => false]);
        $this->rpc($token, 'tools/list')->assertForbidden();
        config(['features.mcp_access' => true, 'features.api_access' => false]);
        $this->rpc($token, 'tools/list')->assertForbidden();
    }

    public function test_saas_checks_the_current_organizations_entitlement_on_every_call(): void
    {
        config(['features.saas' => true]);
        $resolver = new class implements FeatureResolver
        {
            public bool $apiAccess = true;

            public bool $mcpAccess = true;

            public ?string $organizationId = null;

            public function enabled(string $feature): bool
            {
                return (bool) config('features.'.$feature, false);
            }

            public function enabledForOrg(string $feature, mixed $org): bool
            {
                $this->organizationId = $org->id;

                return match ($feature) {
                    'api_access' => $this->apiAccess,
                    'mcp_access' => $this->mcpAccess,
                    default => true,
                };
            }
        };
        app()->instance(FeatureResolver::class, $resolver);
        $token = $this->mcpToken();
        $this->rpc($token, 'tools/list')->assertOk();
        $this->assertSame($this->orgA->id, $resolver->organizationId);
        $resolver->mcpAccess = false;
        $this->rpc($token, 'tools/list')->assertForbidden();
        $resolver->mcpAccess = true;
        $resolver->apiAccess = false;
        $this->rpc($token, 'tools/list')->assertForbidden();
    }

    public function test_unknown_write_tools_cannot_modify_documents(): void
    {
        $invoice = Invoice::factory()->create(['organization_id' => $this->orgA->id]);
        $expense = Expense::factory()->create(['organization_id' => $this->orgA->id]);
        $beforeInvoice = $invoice->fresh()->getRawOriginal();
        $beforeExpense = $expense->fresh()->getRawOriginal();
        $token = $this->mcpToken();
        foreach (['send_invoice', 'approve_expense', 'delete_expense'] as $name) {
            $this->callTool($token, $name, ['id' => $invoice->id])->assertJsonPath('error.code', -32602);
        }
        $this->assertSame($beforeInvoice, $invoice->fresh()->getRawOriginal());
        $this->assertSame($beforeExpense, $expense->fresh()->getRawOriginal());
    }

    public function test_read_only_tokens_cannot_mint_more_powerful_tokens_or_revoke_other_tokens_via_rest(): void
    {
        $token = $this->mcpToken();
        $other = $this->mcpToken(['invoicing.view']);
        $record = PersonalAccessToken::findToken($other);
        Auth::forgetGuards();

        $this->withToken($token)->postJson('/api/v1/tokens', [
            'name' => 'Privilege escalation', 'abilities' => ['*'],
        ])->assertForbidden();
        Auth::forgetGuards();
        $this->withToken($token)->deleteJson('/api/v1/tokens/'.$record->uuid)->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_cross_origin_and_oversized_requests_are_rejected(): void
    {
        $token = $this->mcpToken();
        $this->withHeader('Origin', 'https://untrusted.example')->rpc($token, 'tools/list')->assertForbidden();
        $this->withHeader('Origin', rtrim(config('app.url'), '/'))->rpc($token, 'tools/list')->assertOk();
        $this->rpc($token, 'tools/list', ['padding' => str_repeat('x', 65536)])->assertStatus(413);
    }

    public function test_calls_are_rate_limited(): void
    {
        app('router')->aliasMiddleware('throttle', ThrottleRequests::class);
        $token = $this->mcpToken();
        for ($i = 0; $i < 60; $i++) {
            $this->rpc($token, 'ping')->assertOk();
        }
        $this->rpc($token, 'ping')->assertTooManyRequests();
    }
}
