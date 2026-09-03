<?php

namespace App\Domains\Banking\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\DTOs\CreateBankAccountData;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Queries\BankAccountQuery;
use Illuminate\Support\Facades\DB;

class CreateBankAccountAction
{
    public function execute(CreateBankAccountData $data): BankAccount
    {
        return DB::transaction(function () use ($data): BankAccount {
            $bankAccount = BankAccount::create($data->toArray());
            $this->configure($bankAccount);

            return $bankAccount->load('ledgerAccount');
        });
    }

    public function configure(BankAccount $bankAccount): void
    {
        if ($bankAccount->is_mixed_use) {
            $this->ensurePrivateWithdrawalsAccount($bankAccount->organization_id);
        }

        if ($bankAccount->is_default_for_invoicing) {
            BankAccount::query()
                ->where('organization_id', $bankAccount->organization_id)
                ->where('id', '!=', $bankAccount->id)
                ->where('is_default_for_invoicing', true)
                ->update(['is_default_for_invoicing' => false]);
        }

        BankAccountQuery::forgetSelectCache($bankAccount->organization_id);
    }

    private function ensurePrivateWithdrawalsAccount(string $organizationId): void
    {
        Account::query()->firstOrCreate(
            [
                'organization_id' => $organizationId,
                'code' => AccountCode::PRIVATE_WITHDRAWALS,
            ],
            [
                'name' => __('app.private_withdrawals_account'),
                'type' => AccountType::Equity->value,
                'is_active' => true,
            ],
        );
    }
}
