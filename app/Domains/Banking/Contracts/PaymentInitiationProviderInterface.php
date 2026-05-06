<?php

namespace App\Domains\Banking\Contracts;

use App\Domains\Banking\DTOs\PaymentInitiationResult;
use App\Domains\Banking\DTOs\PaymentInstructionData;
use App\Domains\Banking\Models\BankAccount;

/**
 * Outbound payment provider — produces (or submits) an ISO 20022 pain.001 batch.
 *
 * Implementations:
 *   - FilePain001Provider (CE): generates an XML file the user uploads to e-banking.
 *   - BlinkPaymentProvider  (EE/Phase 2): pushes the same payload to SIX bLink.
 *
 * Selected at runtime by BankAccount.sync_provider, mirroring BankDataSourceInterface.
 */
interface PaymentInitiationProviderInterface
{
    /**
     * @param  PaymentInstructionData[]  $instructions
     */
    public function initiate(BankAccount $debtor, array $instructions): PaymentInitiationResult;
}
