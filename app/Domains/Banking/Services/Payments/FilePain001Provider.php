<?php

namespace App\Domains\Banking\Services\Payments;

use App\Domains\Banking\Contracts\PaymentInitiationProviderInterface;
use App\Domains\Banking\DTOs\PaymentInitiationResult;
use App\Domains\Banking\DTOs\PaymentInstructionData;
use App\Domains\Banking\Models\BankAccount;
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

        $pmtTpInf = $dom->createElement('PmtTpInf');
        $svcLvl = $dom->createElement('SvcLvl');
        $svcLvl->appendChild($dom->createElement('Cd', 'SEPA')); // generic; banks accept for CHF/EUR
        // For CH-domestic, no SvcLvl is fine; keeping minimal "SEPA" gives broad compat.
        $pmtTpInf->appendChild($svcLvl);
        $pmtInf->appendChild($pmtTpInf);

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
     * Normalise an explicit BIC (uppercase, strip whitespace). Returns
     * `null` when no BIC is configured so callers can fall back to
     * `Othr/NOTPROVIDED`. Auto-derivation from Swiss IIDs is intentionally
     * not attempted — emitting an incorrect BIC would route the payment
     * to the wrong bank.
     */
    private function resolveBic(?string $iban, ?string $explicitBic): ?string
    {
        if ($explicitBic === null) {
            return null;
        }

        $bic = strtoupper(preg_replace('/\s+/', '', $explicitBic) ?? '');

        return $bic !== '' ? $bic : null;
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
