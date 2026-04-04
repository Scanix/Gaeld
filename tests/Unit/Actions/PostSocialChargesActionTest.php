<?php

namespace Tests\Unit\Actions;

use App\Domains\Accounting\Actions\PostSocialChargesAction;
use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use Mockery;
use Tests\TestCase;

class PostSocialChargesActionTest extends TestCase
{
    private PostSocialChargesAction $action;

    private $ledgerService;

    private $ledgerQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = Mockery::mock(LedgerService::class);
        $this->ledgerQuery = Mockery::mock(LedgerQueryService::class);
        $this->action = new PostSocialChargesAction($this->ledgerService, $this->ledgerQuery);
    }

    public function test_posts_social_charges_entry(): void
    {
        $socialAccount = Mockery::mock(Account::class)->makePartial();
        $socialAccount->id = 'acct-social';

        $bankAccount = Mockery::mock(Account::class)->makePartial();
        $bankAccount->id = 'acct-bank';

        $this->ledgerQuery
            ->shouldReceive('resolveAccount')
            ->with('org-1', AccountCode::SOCIAL_CHARGES)
            ->once()
            ->andReturn($socialAccount);

        $this->ledgerQuery
            ->shouldReceive('resolveAccount')
            ->with('org-1', AccountCode::BANK_CASH)
            ->once()
            ->andReturn($bankAccount);

        $journalEntry = Mockery::mock(JournalEntry::class)->makePartial();
        $journalEntry->id = 42;

        $this->ledgerService
            ->shouldReceive('postEntry')
            ->once()
            ->withArgs(function (string $orgId, JournalEntryData $data) {
                return $orgId === 'org-1'
                    && $data->description === 'AVS/AI/APG Q1 2026'
                    && count($data->lines) === 2
                    && $data->lines[0]->debit === '1250.00'
                    && $data->lines[0]->credit === '0.00'
                    && $data->lines[1]->debit === '0.00'
                    && $data->lines[1]->credit === '1250.00';
            })
            ->andReturn($journalEntry);

        $journalEntry->shouldReceive('update')
            ->once()
            ->with(['type' => 'social_charges']);

        $result = $this->action->execute('org-1', '1250.00', 'AVS/AI/APG Q1 2026', '2026-03-31');

        $this->assertSame($journalEntry, $result);
        $this->assertSame(42, $result->id);
    }

    public function test_uses_current_date_when_not_specified(): void
    {
        $socialAccount = Mockery::mock(Account::class)->makePartial();
        $socialAccount->id = 'acct-social';

        $bankAccount = Mockery::mock(Account::class)->makePartial();
        $bankAccount->id = 'acct-bank';

        $this->ledgerQuery
            ->shouldReceive('resolveAccount')
            ->twice()
            ->andReturn($socialAccount, $bankAccount);

        $journalEntry = Mockery::mock(JournalEntry::class)->makePartial();
        $journalEntry->id = 43;

        $today = now()->format('Y-m-d');

        $this->ledgerService
            ->shouldReceive('postEntry')
            ->once()
            ->withArgs(function (string $orgId, JournalEntryData $data) use ($today) {
                return $data->date === $today;
            })
            ->andReturn($journalEntry);

        $journalEntry->shouldReceive('update')->once();

        $result = $this->action->execute('org-1', '500.00', 'Social charges');

        $this->assertSame($journalEntry, $result);
        $this->assertSame(43, $result->id);
    }

    public function test_debits_social_charges_and_credits_bank(): void
    {
        $socialAccount = Mockery::mock(Account::class)->makePartial();
        $socialAccount->id = 'acct-social';

        $bankAccount = Mockery::mock(Account::class)->makePartial();
        $bankAccount->id = 'acct-bank';

        $this->ledgerQuery
            ->shouldReceive('resolveAccount')
            ->with('org-1', AccountCode::SOCIAL_CHARGES)
            ->once()
            ->andReturn($socialAccount);

        $this->ledgerQuery
            ->shouldReceive('resolveAccount')
            ->with('org-1', AccountCode::BANK_CASH)
            ->once()
            ->andReturn($bankAccount);

        $journalEntry = Mockery::mock(JournalEntry::class)->makePartial();
        $journalEntry->id = 44;

        $this->ledgerService
            ->shouldReceive('postEntry')
            ->once()
            ->withArgs(function (string $orgId, JournalEntryData $data) {
                $debitLine = $data->lines[0];
                $creditLine = $data->lines[1];

                // Account IDs are cast via (string), verify debit/credit structure
                return $orgId === 'org-1'
                    && $debitLine->debit === '750.00'
                    && $debitLine->credit === '0.00'
                    && $creditLine->debit === '0.00'
                    && $creditLine->credit === '750.00';
            })
            ->andReturn($journalEntry);

        $journalEntry->shouldReceive('update')->once();

        $result = $this->action->execute('org-1', '750.00', 'Swiss social charges', '2026-06-30');

        $this->assertSame($journalEntry, $result);
        $this->assertSame(44, $result->id);
    }
}
