<?php

namespace Tests\Unit\Migration;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Migration\DTOs\AccountImportRow;
use App\Domains\Migration\DTOs\ContactImportRow;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Importers\AccountImporter;
use App\Domains\Migration\Importers\ContactImporter;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImporterTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Importer Test Org',
            'currency' => 'CHF',
        ]);
    }

    // ────────────────────────────────────────────────
    // AccountImporter
    // ────────────────────────────────────────────────

    public function test_account_importer_returns_correct_data_type(): void
    {
        $importer = app(AccountImporter::class);

        $this->assertSame(DataType::Accounts, $importer->dataType());
        $this->assertEmpty($importer->dependencies());
    }

    public function test_account_importer_validates_valid_rows(): void
    {
        $importer = app(AccountImporter::class);

        $rows = collect([
            new AccountImportRow(1, '1020', 'Bank', 'asset'),
            new AccountImportRow(2, '3000', 'Revenue', 'revenue'),
        ]);

        $result = $importer->validate($rows, $this->organization);

        $this->assertTrue($result->valid);
    }

    public function test_account_importer_validates_invalid_rows(): void
    {
        $importer = app(AccountImporter::class);

        $rows = collect([
            new AccountImportRow(1, '1020', 'Bank', 'invalid_type'),
        ]);

        $result = $importer->validate($rows, $this->organization);

        $this->assertFalse($result->valid);
    }

    public function test_account_importer_creates_accounts(): void
    {
        $importer = app(AccountImporter::class);

        $rows = collect([
            new AccountImportRow(1, '1020', 'Bank', 'asset', 'Main bank'),
            new AccountImportRow(2, '3000', 'Revenue', 'revenue'),
        ]);

        $result = $importer->import($rows, $this->organization);

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->importedCount);
        $this->assertDatabaseHas('accounts', [
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank',
        ]);
        $this->assertDatabaseHas('accounts', [
            'organization_id' => $this->organization->id,
            'code' => '3000',
            'name' => 'Revenue',
        ]);
    }

    public function test_account_importer_skips_invalid_rows(): void
    {
        $importer = app(AccountImporter::class);

        $valid = new AccountImportRow(1, '1020', 'Bank', 'asset');
        $invalid = new AccountImportRow(2, '9999', 'Bad', 'asset');
        $invalid->markInvalid();

        $rows = collect([$valid, $invalid]);

        $result = $importer->import($rows, $this->organization);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->importedCount);
        $this->assertSame(1, $result->skippedCount);
    }

    public function test_account_importer_returns_failure_for_no_valid_rows(): void
    {
        $importer = app(AccountImporter::class);

        $invalid = new AccountImportRow(1, '1020', 'Bank', 'asset');
        $invalid->markInvalid();

        $result = $importer->import(collect([$invalid]), $this->organization);

        $this->assertFalse($result->success);
    }

    // ────────────────────────────────────────────────
    // ContactImporter
    // ────────────────────────────────────────────────

    public function test_contact_importer_returns_correct_data_type(): void
    {
        $importer = new ContactImporter;

        $this->assertSame(DataType::Contacts, $importer->dataType());
        $this->assertEmpty($importer->dependencies());
    }

    public function test_contact_importer_validates_valid_rows(): void
    {
        $importer = new ContactImporter;

        $rows = collect([
            new ContactImportRow(1, 'customer', 'Acme Corp', 'acme@test.ch'),
            new ContactImportRow(2, 'supplier', 'Paper Inc'),
        ]);

        $result = $importer->validate($rows, $this->organization);

        $this->assertTrue($result->valid);
    }

    public function test_contact_importer_validates_missing_name(): void
    {
        $importer = new ContactImporter;

        $rows = collect([
            new ContactImportRow(1, 'customer', ''),
        ]);

        $result = $importer->validate($rows, $this->organization);

        $this->assertFalse($result->valid);
    }

    public function test_contact_importer_validates_invalid_type(): void
    {
        $importer = new ContactImporter;

        $rows = collect([
            new ContactImportRow(1, 'partner', 'Acme Corp'),
        ]);

        $result = $importer->validate($rows, $this->organization);

        $this->assertFalse($result->valid);
    }

    public function test_contact_importer_creates_customers(): void
    {
        $importer = new ContactImporter;

        $rows = collect([
            new ContactImportRow(1, 'customer', 'Acme Corp', 'acme@test.ch', '+41791234567', 'Hauptstrasse 1', '3000', 'Bern', 'CH'),
        ]);

        $result = $importer->import($rows, $this->organization);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->importedCount);
        $this->assertDatabaseHas('customers', [
            'organization_id' => $this->organization->id,
            'name' => 'Acme Corp',
            'email' => 'acme@test.ch',
            'city' => 'Bern',
        ]);
    }

    public function test_contact_importer_creates_suppliers(): void
    {
        $importer = new ContactImporter;

        $rows = collect([
            new ContactImportRow(1, 'supplier', 'Paper Inc', 'paper@test.ch'),
        ]);

        $result = $importer->import($rows, $this->organization);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->importedCount);
        $this->assertDatabaseHas('suppliers', [
            'organization_id' => $this->organization->id,
            'name' => 'Paper Inc',
            'email' => 'paper@test.ch',
        ]);
    }

    public function test_contact_importer_deduplicates_by_name_and_email(): void
    {
        $importer = new ContactImporter;

        // Pre-create customer
        Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme Corp',
            'email' => 'acme@test.ch',
        ]);

        $rows = collect([
            new ContactImportRow(1, 'customer', 'Acme Corp', 'acme@test.ch'),
            new ContactImportRow(2, 'customer', 'New Corp', 'new@test.ch'),
        ]);

        $result = $importer->import($rows, $this->organization);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->importedCount);
        $this->assertSame(1, $result->skippedCount);
        $this->assertSame(2, Customer::where('organization_id', $this->organization->id)->count());
    }

    public function test_contact_importer_defaults_country_to_ch(): void
    {
        $importer = new ContactImporter;

        $rows = collect([
            new ContactImportRow(1, 'customer', 'No Country Corp'),
        ]);

        $result = $importer->import($rows, $this->organization);

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('customers', [
            'name' => 'No Country Corp',
            'country' => 'CH',
        ]);
    }

    public function test_contact_importer_skips_invalid_rows(): void
    {
        $importer = new ContactImporter;

        $valid = new ContactImportRow(1, 'customer', 'Valid Corp');
        $invalid = new ContactImportRow(2, 'customer', 'Invalid Corp');
        $invalid->markInvalid();

        $rows = collect([$valid, $invalid]);

        $result = $importer->import($rows, $this->organization);

        $this->assertSame(1, $result->importedCount);
        $this->assertSame(1, $result->skippedCount);
    }
}
