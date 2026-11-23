<?php

namespace Tests\Feature\Api;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Api\Enums\TokenType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Models\InvoiceCatalogItem;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Security\SecurityTestCase;

class InvoiceReferenceDataTest extends SecurityTestCase
{
    public static function tokenTypes(): array
    {
        return [[TokenType::Personal], [TokenType::Organization]];
    }

    #[DataProvider('tokenTypes')]
    public function test_reference_data_is_scoped_and_uses_invoice_compatible_ids(TokenType $type): void
    {
        app(CurrentOrganization::class)->set($this->orgB);
        $otherRate = VatRate::factory()->create(['organization_id' => $this->orgB->id]);
        $otherItem = InvoiceCatalogItem::create(['name' => 'Other service']);

        app(CurrentOrganization::class)->set($this->orgA);
        $rate = VatRate::factory()->create(['organization_id' => $this->orgA->id, 'rate' => '8.10']);
        $item = InvoiceCatalogItem::create([
            'name' => 'Service',
            'default_unit_price' => '25.00',
            'default_vat_rate_id' => $rate->id,
        ]);
        $customer = Contact::create(['name' => 'Integration customer']);
        $token = $this->token($type, [Permission::AccountingView->value, Permission::InvoicingView->value, Permission::InvoicingCreate->value]);
        $this->withToken($token)->getJson('/api/v1/vat-rates?per_page=1')
            ->assertOk()->assertJsonPath('meta.per_page', 1)->assertJsonMissing(['id' => $otherRate->uuid]);
        $this->getJson('/api/v1/vat-rates/'.$rate->uuid)
            ->assertOk()->assertJsonPath('data.id', $rate->uuid)->assertJsonPath('data.rate', '8.10');
        $this->getJson('/api/v1/vat-rates/'.$otherRate->uuid)->assertNotFound();
        $this->getJson('/api/v1/invoice-catalog-items/'.$otherItem->id)->assertNotFound();
        $this->getJson('/api/v1/invoice-catalog-items')->assertOk()
            ->assertJsonFragment(['id' => $item->id])->assertJsonMissing(['id' => $otherItem->id]);
        $defaults = $this->getJson('/api/v1/invoice-catalog-items/'.$item->id)
            ->assertOk()->assertJsonPath('data.default_vat_rate_id', $rate->uuid)->json('data');

        $this->postJson('/api/v1/invoices', [
            'customer_id' => $customer->uuid, 'number' => 'REF-001', 'issue_date' => '2026-11-23',
            'lines' => [[
                'description' => $defaults['name'], 'quantity' => 1,
                'unit_price' => $defaults['default_unit_price'], 'vat_rate_id' => $defaults['default_vat_rate_id'],
            ]],
        ])->assertCreated()->assertJsonPath('data.lines.0.vat_rate_id', $rate->uuid);
    }

    #[DataProvider('tokenTypes')]
    public function test_reference_reads_require_their_respective_abilities(TokenType $type): void
    {
        $token = $this->token($type, [Permission::AccountingView->value]);
        $this->withToken($token)->getJson('/api/v1/invoice-catalog-items')->assertForbidden();
        Auth::forgetGuards();
        $token = $this->token($type, [Permission::InvoicingView->value]);
        $this->withToken($token)->getJson('/api/v1/vat-rates')->assertForbidden();
        $this->getJson('/api/v1/invoice-catalog-items?per_page=0')->assertUnprocessable();
    }

    /** @param list<string> $abilities */
    private function token(TokenType $type, array $abilities): string
    {
        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);
        $token = $this->ownerA->createToken('reference-data-test', $abilities);
        $token->accessToken->update(['organization_id' => $this->orgA->id, 'type' => $type]);

        return $token->plainTextToken;
    }
}
