<?php

namespace Tests\Security\Api;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Api\Enums\TokenType;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Tests\Security\SecurityTestCase;

class BusinessApiSecurityTest extends SecurityTestCase
{
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);
        $this->tokenA = $this->createApiToken($this->ownerA, $this->orgA);
    }

    public function test_a_token_cannot_read_a_contact_from_another_organization(): void
    {
        $contact = Contact::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Private Org B Contact',
        ]);

        $response = $this->withToken($this->tokenA)
            ->getJson("/api/v1/contacts/{$contact->uuid}");

        $this->assertDenied($response);
    }

    public function test_a_token_without_contact_write_access_cannot_create_a_contact(): void
    {
        $token = $this->ownerA->createToken('contact-read-only', ['contacts.view']);
        $token->accessToken->update([
            'organization_id' => $this->orgA->id,
            'type' => TokenType::Personal,
        ]);

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/contacts', ['name' => 'Not allowed']);

        $response->assertForbidden();
    }

    public function test_a_token_without_banking_import_access_cannot_import_a_statement(): void
    {
        $ledgerAccount = Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        $bankAccount = BankAccount::create([
            'organization_id' => $this->orgA->id,
            'account_id' => $ledgerAccount->id,
            'name' => 'Main bank',
            'currency' => 'CHF',
            'balance' => '0.00',
        ]);
        $token = $this->ownerA->createToken('bank-read-only', ['banking.view']);
        $token->accessToken->update([
            'organization_id' => $this->orgA->id,
            'type' => TokenType::Personal,
        ]);

        $response = $this->withToken($token->plainTextToken)
            ->post("/api/v1/bank-accounts/{$bankAccount->uuid}/imports/camt053", [
                'camt_file' => UploadedFile::fake()->createWithContent('statement.xml', '<Document />'),
            ]);

        $response->assertForbidden();
    }
}
