<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use TCPDF;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class JournalEntryAttachmentJourneyTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    public function test_adobe_debit_and_credit_keep_their_separate_pdfs_after_posting_and_in_reconciliation(): void
    {
        $this->setUpOrganization();
        Storage::fake('local');
        $period = FiscalYear::factory()->for($this->organization)->operative()->create();
        $bank = Account::factory()->for($this->organization)->create(['code' => '1020', 'type' => AccountType::Asset]);
        $software = Account::factory()->for($this->organization)->create(['code' => '6500', 'type' => AccountType::Expense]);
        $bankAccount = BankAccount::factory()->for($this->organization)->create(['account_id' => $bank->id]);

        $debit = $this->recordDraft('UBS-ADOBE-DEBIT', $period, $bank, $software, '35.65');
        $credit = $this->recordDraft('UBS-ADOBE-CREDIT', $period, $software, $bank, '35.08');
        $debitTransaction = $this->recordBankTransaction($bankAccount, $debit->reference, BankTransactionType::Debit, '35.65', $debit);
        $creditTransaction = $this->recordBankTransaction($bankAccount, $credit->reference, BankTransactionType::Credit, '35.08', $credit);
        $referenceOnly = $this->recordBankTransaction($bankAccount, $credit->reference, BankTransactionType::Credit, '35.08');

        $invoice = $this->pdf('Facture Adobe 35.65 CHF');
        $creditNote = $this->pdf('Avoir Adobe 35.08 CHF');
        $receipt = $this->pdf('Preuve de paiement');
        $linesBefore = $debit->lines()->orderBy('id')->get()->toArray();
        $bankBefore = $debitTransaction->fresh()->getRawOriginal();

        $this->upload($debit, 'facture.pdf', $invoice);
        $this->upload($credit, 'avoir.pdf', $creditNote);
        $this->upload($debit, 'seconde-copie.pdf', $invoice);
        $this->upload($credit, 'seconde-copie.pdf', $creditNote);
        $this->upload($debit, 'paiement.pdf', $receipt);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseCount('journal_entry_attachments', 3);

        $debitAttachment = $debit->attachments()->where('original_filename', 'facture.pdf')->firstOrFail();
        $creditAttachment = $credit->attachments()->sole();
        $this->actAsOrg()->post(route('accounting.journal-entries.post', $debit))->assertRedirect();
        $this->assertTrue($debit->fresh()->is_posted);
        $this->assertSame($linesBefore, $debit->lines()->orderBy('id')->get()->toArray());
        $this->assertSame($bankBefore, $debitTransaction->fresh()->getRawOriginal());

        $this->actAsOrg()->get(route('accounting.journal-entries.show', $debit))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/JournalEntryShow')
                ->has('attachments', 2)
                ->where('attachments.0.id', $debitAttachment->id)
                ->where('attachments.0.filename', 'facture.pdf')
                ->where('attachments.0.added_by', $this->user->name)
                ->where('attachments.0.remove_url', null)
                ->where('uploadAttachmentUrl', route('accounting.journal-entries.attachments.store', $debit)));

        $report = $this->actAsOrg()->get(route('reconciliation.show', ['bankAccount' => $bankAccount, 'filter' => 'all']))
            ->assertOk()
            ->viewData('page');
        $transactions = collect($report['props']['transactions']['data'])->keyBy('id');
        $this->assertSame($debitAttachment->id, $transactions[$debitTransaction->id]['journal_entry']['attachments'][0]['id']);
        $creditEntry = $transactions[$creditTransaction->id]['journal_entry'];
        $this->assertSame(route('accounting.journal-entries.show', $credit), $creditEntry['show_url']);
        $this->assertSame($creditAttachment->id, $creditEntry['attachments'][0]['id']);
        $this->assertNull($transactions[$referenceOnly->id]['journal_entry']);
        $this->assertArrayNotHasKey('storage_path', $creditEntry['attachments'][0]);
        $this->assertArrayNotHasKey('issued_document_file_id', $creditEntry['attachments'][0]);

        $bankViewer = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->organization->users()->attach($bankViewer->id, ['role' => 'viewer']);
        $bankViewer->givePermissionTo(Permission::BankingView);
        $report = $this->actingAs($bankViewer)->get(route('reconciliation.show', ['bankAccount' => $bankAccount, 'filter' => 'all']))
            ->assertOk()
            ->viewData('page');
        $transactions = collect($report['props']['transactions']['data'])->keyBy('id');
        $this->assertArrayNotHasKey('attachments', $transactions[$creditTransaction->id]['journal_entry']);
    }

    private function recordDraft(string $reference, FiscalYear $period, Account $credited, Account $debited, string $amount): JournalEntry
    {
        $entry = JournalEntry::create([
            'organization_id' => $this->organization->id,
            'reference' => $reference,
            'date' => $period->start_date->addDay(),
            'is_posted' => false,
        ]);

        TransactionLine::create(['journal_entry_id' => $entry->id, 'account_id' => $credited->id, 'debit' => '0.00', 'credit' => $amount]);
        TransactionLine::create(['journal_entry_id' => $entry->id, 'account_id' => $debited->id, 'debit' => $amount, 'credit' => '0.00']);

        return $entry;
    }

    private function recordBankTransaction(
        BankAccount $bankAccount,
        string $reference,
        BankTransactionType $type,
        string $amount,
        ?JournalEntry $entry = null,
    ): BankTransaction {
        return BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'journal_entry_id' => $entry?->id,
            'date' => $entry?->date ?? now(),
            'reference' => $reference,
            'type' => $type,
            'amount' => $amount,
            'import_hash' => hash('sha256', $reference.($entry?->id ?? 'reference-only')),
            'is_reconciled' => false,
        ]);
    }

    private function upload(JournalEntry $entry, string $filename, string $bytes): void
    {
        $this->actAsOrg()->post(route('accounting.journal-entries.attachments.store', $entry), [
            'file' => UploadedFile::fake()->createWithContent($filename, $bytes),
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function pdf(string $text): string
    {
        $document = new TCPDF;
        $document->AddPage();
        $document->Write(0, $text);

        return $document->Output('', 'S');
    }
}
