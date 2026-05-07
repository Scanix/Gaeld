<?php

namespace App\Domains\Banking\Services\Payments;

use App\Domains\Banking\Contracts\PaymentInitiationProviderInterface;
use App\Domains\Banking\DTOs\PaymentInitiationResult;
use App\Domains\Banking\DTOs\PaymentInstructionData;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Services\SwissBicResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates an ISO 20022 pain.001.001.09 (Customer Credit Transfer Initiation)
 * XML file the user uploads to their e-banking.
 *
 * Default CE provider — superseded by BlinkPaymentProvider when the EE
 * bank_sync feature is enabled and the BankAccount has sync_provider=blink.
 */
class FilePain001Provider implements PaymentInitiationProviderInterface
{
    private const NAMESPACE_URI = 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09';

    public function __construct(private readonly SwissBicResolver $bicResolver) {}

    /**
     * @param  PaymentInstructionData[]  $instructions
     */
    public function initiate(BankAccount $debtor, array $instructions): PaymentInitiationResult
    {
        if ($instructions === []) {
            throw new \InvalidArgumentException('Cannot generate pain.001 with no instructions.');
        }

        if (! $debtor->iban) {
            throw new \InvalidArgumentException("Debtor bank account {$debtor->id} has no IBAN.");
        }

        $currency = $instructions[0]->currency;
        foreach ($instructions as $instr) {
            if ($instr->currency !== $currency) {
                throw new \InvalidArgumentException('All instructions in a batch must share the same currency.');
            }
        }

        $xml = $this->build($debtor, $instructions, $currency);
        $totalAmount = $this->controlSum($instructions);
        $filename = 'pain001-'.preg_replace('/[^A-Z0-9]/i', '', $debtor->iban).'-'.Carbon::now()->format('YmdHis').'.xml';

        $response = new Response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);

        return PaymentInitiationResult::file($response, count($instructions), $totalAmount, $currency);
    }

    /**
     * @param  PaymentInstructionData[]  $instructions
     */
    private function build(BankAccount $debtor, array $instructions, string $currency): string
    {
        $org = $debtor->organization;
        $now = Carbon::now();
        $msgId = $this->messageId($org->id ?? 'NA');
        $pmtInfId = 'PMT-'.Str::upper(Str::random(12));
        $controlSum = $this->controlSum($instructions);
        $execDate = $instructions[0]->executionDate->toDateString();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NAMESPACE_URI, 'Document');
        $dom->appendChild($root);

        $cstmr = $dom->createElement('CstmrCdtTrfInitn');
        $root->appendChild($cstmr);

        // ── Group Header ──
        $grpHdr = $dom->createElement('GrpHdr');
        $cstmr->appendChild($grpHdr);
        $grpHdr->appendChild($dom->createElement('MsgId', $msgId));
        $grpHdr->appendChild($dom->createElement('CreDtTm', $now->toIso8601String()));
        $grpHdr->appendChild($dom->createElement('NbOfTxs', (string) count($instructions)));
        $grpHdr->appendChild($dom->createElement('CtrlSum', $controlSum));

        $initgPty = $dom->createElement('InitgPty');
        $initgPty->appendChild($dom->createElement('Nm', $this->sanitize($org->legal_name ?: $org->name)));
        $grpHdr->appendChild($initgPty);

        // ── Payment Information (one block, single currency / single execution date) ──
        $pmtInf = $dom->createElement('PmtInf');
        $cstmr->appendChild($pmtInf);
        $pmtInf->appendChild($dom->createElement('PmtInfId', $pmtInfId));
        $pmtInf->appendChild($dom->createElement('PmtMtd', 'TRF'));
        $pmtInf->appendChild($dom->createElement('BtchBookg', 'true'));
        $pmtInf->appendChild($dom->createElement('NbOfTxs', (string) count($instructions)));
        $pmtInf->appendChild($dom->createElement('CtrlSum', $controlSum));

        // PmtTpInf is omitted for SPS Type 3 (Swiss-domestic CHF/EUR) which covers
        // the vast majority of payments. SEPA SvcLvl is EUR-only and would cause
        // strict banks (e.g. UBS) to reject CHF payments with "format invalide".
        // Only emit SvcLvl/SEPA when the payment is actually a SEPA EUR payment
        // from a CH/LI debtor to a non-CH/LI SEPA-zone creditor.
        $pmtTpInf = $this->buildPmtTpInf($dom, $debtor, $instructions, $currency);
        if ($pmtTpInf !== null) {
            $pmtInf->appendChild($pmtTpInf);
        }

        // pain.001.001.09: ReqdExctnDt is element-only and must wrap the date in <Dt> (or <DtTm>).
        $reqdExctnDt = $dom->createElement('ReqdExctnDt');
        $reqdExctnDt->appendChild($dom->createElement('Dt', $execDate));
        $pmtInf->appendChild($reqdExctnDt);

        $dbtr = $dom->createElement('Dbtr');
        $dbtr->appendChild($dom->createElement('Nm', $this->sanitize($org->legal_name ?: $org->name)));
        $pmtInf->appendChild($dbtr);

        $dbtrAcct = $dom->createElement('DbtrAcct');
        $dbtrAcctId = $dom->createElement('Id');
        $dbtrAcctId->appendChild($dom->createElement('IBAN', $this->normalizeIban($debtor->iban)));
        $dbtrAcct->appendChild($dbtrAcctId);
        $pmtInf->appendChild($dbtrAcct);

        $dbtrAgt = $dom->createElement('DbtrAgt');
        $dbtrAgt->appendChild($this->buildFinInstnId($dom, $debtor->iban, $debtor->bic));
        $pmtInf->appendChild($dbtrAgt);

        $pmtInf->appendChild($dom->createElement('ChrgBr', 'SLEV'));

        // ── Credit Transfer Transactions ──
        foreach ($instructions as $instr) {
            $pmtInf->appendChild($this->buildTransaction($dom, $instr));
        }

        return $dom->saveXML() ?: '';
    }

    private function buildTransaction(\DOMDocument $dom, PaymentInstructionData $instr): \DOMElement
    {
        $tx = $dom->createElement('CdtTrfTxInf');

        $pmtId = $dom->createElement('PmtId');
        $pmtId->appendChild($dom->createElement('EndToEndId', $this->sanitize($instr->endToEndId, 35)));
        $tx->appendChild($pmtId);

        $amt = $dom->createElement('Amt');
        $instdAmt = $dom->createElement('InstdAmt', $instr->amount);
        $instdAmt->setAttribute('Ccy', $instr->currency);
        $amt->appendChild($instdAmt);
        $tx->appendChild($amt);

        $cdtrAgt = $dom->createElement('CdtrAgt');
        $cdtrAgt->appendChild($this->buildFinInstnId($dom, $instr->creditorIban, $instr->creditorBic));
        $tx->appendChild($cdtrAgt);

        $cdtr = $dom->createElement('Cdtr');
        $cdtr->appendChild($dom->createElement('Nm', $this->sanitize($instr->creditorName, 70)));
        $tx->appendChild($cdtr);

        $cdtrAcct = $dom->createElement('CdtrAcct');
        $cdtrAcctId = $dom->createElement('Id');
        $cdtrAcctId->appendChild($dom->createElement('IBAN', $instr->creditorIban));
        $cdtrAcct->appendChild($cdtrAcctId);
        $tx->appendChild($cdtrAcct);

        // Remittance: structured (QR-bill) > SCOR > unstructured
        $rmtInf = $dom->createElement('RmtInf');
        if ($instr->isQrReference()) {
            $strd = $dom->createElement('Strd');
            $cdtrRefInf = $dom->createElement('CdtrRefInf');
            $tp = $dom->createElement('Tp');
            $cdOrPrtry = $dom->createElement('CdOrPrtry');
            $prtry = $dom->createElement('Prtry', 'QRR');
            $cdOrPrtry->appendChild($prtry);
            $tp->appendChild($cdOrPrtry);
            $cdtrRefInf->appendChild($tp);
            $cdtrRefInf->appendChild($dom->createElement('Ref', $instr->structuredReference));
            $strd->appendChild($cdtrRefInf);
            $rmtInf->appendChild($strd);
        } elseif ($instr->isScorReference()) {
            $strd = $dom->createElement('Strd');
            $cdtrRefInf = $dom->createElement('CdtrRefInf');
            $tp = $dom->createElement('Tp');
            $cdOrPrtry = $dom->createElement('CdOrPrtry');
            $cd = $dom->createElement('Cd', 'SCOR');
            $cdOrPrtry->appendChild($cd);
            $tp->appendChild($cdOrPrtry);
            $cdtrRefInf->appendChild($tp);
            $cdtrRefInf->appendChild($dom->createElement('Ref', $instr->structuredReference));
            $strd->appendChild($cdtrRefInf);
            $rmtInf->appendChild($strd);
        } elseif ($instr->unstructuredRemittance) {
            $rmtInf->appendChild($dom->createElement('Ustrd', $this->sanitize($instr->unstructuredRemittance, 140)));
        }
        $tx->appendChild($rmtInf);

        return $tx;
    }

    /**
     * @param  PaymentInstructionData[]  $instructions
     */
    private function controlSum(array $instructions): string
    {
        $sum = 0.0;
        foreach ($instructions as $instr) {
            $sum += (float) $instr->amount;
        }

        return number_format($sum, 2, '.', '');
    }

    private function messageId(string $orgId): string
    {
        $short = substr(str_replace('-', '', $orgId), 0, 8);

        return 'GAELD-'.$short.'-'.Carbon::now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    }

    /**
     * Build a `<FinInstnId>` element. Emits `<BICFI>` when a BIC is known
     * (explicit or derived from a Swiss IID), otherwise falls back to
     * `<Othr><Id>NOTPROVIDED</Id></Othr>` per ISO 20022.
     *
     * Some banks (notably UBS) enforce a strict XSD profile that only
     * accepts `<BICFI>` here; users on such banks must populate the BIC
     * field on their bank account / supplier contact.
     */
    private function buildFinInstnId(\DOMDocument $dom, ?string $iban, ?string $explicitBic): \DOMElement
    {
        $finInstnId = $dom->createElement('FinInstnId');
        $bic = $this->resolveBic($iban, $explicitBic);

        if ($bic !== null) {
            $finInstnId->appendChild($dom->createElement('BICFI', $bic));

            return $finInstnId;
        }

        $othr = $dom->createElement('Othr');
        $othr->appendChild($dom->createElement('Id', 'NOTPROVIDED'));
        $finInstnId->appendChild($othr);

        return $finInstnId;
    }

    /**
     * Resolve a BIC for a given (optional) IBAN + explicit BIC. Explicit BIC
     * always wins. When absent, falls back to a curated CH/LI IID lookup that
     * only returns a BIC when the issuing institution is unambiguous; returns
     * `null` otherwise so the caller can emit `Othr/NOTPROVIDED`.
     */
    private function resolveBic(?string $iban, ?string $explicitBic): ?string
    {
        if ($explicitBic !== null) {
            $normalized = strtoupper(preg_replace('/\s+/', '', $explicitBic) ?? '');
            if ($normalized !== '') {
                return $normalized;
            }
        }

        // Fallback: derive from Swiss/LI IBAN via curated IID table.
        // Only returns a BIC when the IID is unambiguously known.
        return $this->bicResolver->resolveFromIban($iban);
    }

    /**
     * Build PmtTpInf only when a service level is actually required.
     *
     * Per Swiss Payment Standards:
     *  - Type 1 (SEPA): SvcLvl/SEPA, EUR only, debtor in CH/LI, creditor in SEPA zone.
     *  - Type 3 (Swiss-domestic): no SvcLvl, no LclInstrm.
     *  - Type 2 (foreign): no SvcLvl.
     *
     * @param  PaymentInstructionData[]  $instructions
     */
    private function buildPmtTpInf(
        \DOMDocument $dom,
        BankAccount $debtor,
        array $instructions,
        string $currency,
    ): ?\DOMElement {
        if ($currency !== 'EUR') {
            return null;
        }

        $debtorCountry = strtoupper(substr($this->normalizeIban($debtor->iban ?? ''), 0, 2));
        if (! in_array($debtorCountry, ['CH', 'LI'], true)) {
            return null;
        }

        // All creditors must sit in the SEPA zone for the SEPA service level
        // to be valid. If any single creditor is non-SEPA, drop SvcLvl.
        foreach ($instructions as $instr) {
            $cdtrCountry = strtoupper(substr($this->normalizeIban($instr->creditorIban), 0, 2));
            if (! $this->isSepaCountry($cdtrCountry)) {
                return null;
            }
        }

        $pmtTpInf = $dom->createElement('PmtTpInf');
        $svcLvl = $dom->createElement('SvcLvl');
        $svcLvl->appendChild($dom->createElement('Cd', 'SEPA'));
        $pmtTpInf->appendChild($svcLvl);

        return $pmtTpInf;
    }

    private function isSepaCountry(string $cc): bool
    {
        // SEPA member countries (EU + EEA + UK + CH + LI + MC + SM + AD + VA).
        static $sepa = [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
            'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL',
            'PT', 'RO', 'SE', 'SI', 'SK', 'IS', 'LI', 'NO', 'CH', 'GB', 'MC',
            'SM', 'AD', 'VA',
        ];

        return in_array($cc, $sepa, true);
    }

    /**
     * Strip control chars and trim to ISO 20022 max length.
     */
    private function sanitize(string $value, int $maxLength = 70): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? $value;
        $clean = trim($clean);

        return mb_substr($clean, 0, $maxLength);
    }
}
