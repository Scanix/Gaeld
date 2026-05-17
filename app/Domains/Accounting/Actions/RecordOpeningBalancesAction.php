<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Record user-supplied opening balances for a fresh fiscal year.
 *
 * Unlike {@see GenerateOpeningBalancesAction} (which carries balances forward
 * from a previously closed year), this action is used when a customer
 * starts using Gäld mid-life and needs to seed balance-sheet accounts
 * from their prior bookkeeping system.
 *
 * The amount per row is signed and expressed on the account's natural
 * side: positive means a normal balance (debit for assets/expenses,
 * credit for liabilities/equity/revenue). Any imbalance is plugged into
 * the opening-balance account (9000).
 */
class RecordOpeningBalancesAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly LedgerQueryService $ledgerQuery,
    ) {}

    /**
     * @param  array<int, array{account_id: int|string, amount: string}>  $balances
     */
    public function execute(
        string $orgId,
        string $date,
        array $balances,
        ?string $reference = null,
        ?string $description = null,
    ): ?JournalEntry {
        $year = Carbon::parse($date)->year;

        $accountIds = array_unique(array_map(static fn (array $r) => (int) $r['account_id'], $balances));

        /** @var array<int, Account> $accounts */
        $accounts = Account::where('organization_id', $orgId)
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id')
            ->all();

        $openingAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::OPENING_BALANCE);

        $lines = [];
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($balances as $row) {
            $accountId = (int) $row['account_id'];
            $amount = Money::of((string) $row['amount']);

            if (Money::isZero($amount)) {
                continue;
            }

            $account = $accounts[$accountId] ?? null;
            if ($account === null) {
                continue;
            }

            // The amount is signed on the account's natural side. Negative
            // amounts flip to the opposite side.
            $isDebitNormal = $account->type->isDebitNormal();
            $isPositive = Money::isPositive($amount);
            $absAmount = $isPositive ? $amount : Money::negate($amount);
            $shouldDebit = $isPositive === $isDebitNormal;

            $lines[] = new JournalLineData(
                accountId: (string) $account->id,
                debit: $shouldDebit ? $absAmount : '0',
                credit: $shouldDebit ? '0' : $absAmount,
                description: __('app.opening_balance_for_account', ['code' => $account->code]),
            );

            if ($shouldDebit) {
                $totalDebit = Money::add($totalDebit, $absAmount);
            } else {
                $totalCredit = Money::add($totalCredit, $absAmount);
            }
        }

        if (empty($lines)) {
            return null;
        }

        // Balance via the opening-balance (9000) account.
        $diff = Money::subtract($totalDebit, $totalCredit);

        if (Money::isPositive($diff)) {
            $lines[] = new JournalLineData(
                accountId: (string) $openingAccount->id,
                debit: '0',
                credit: $diff,
                description: __('app.opening_balance_contra'),
            );
        } elseif (Money::isNegative($diff)) {
            $lines[] = new JournalLineData(
                accountId: (string) $openingAccount->id,
                debit: Money::negate($diff),
                credit: '0',
                description: __('app.opening_balance_contra'),
            );
        }

        return $this->ledger->postEntry($orgId, new JournalEntryData(
            date: $date,
            reference: $reference ?? "OPENING-{$year}",
            description: $description ?? __('app.opening_balance_entry_description', ['year' => $year]),
            lines: $lines,
        ));
    }
}
