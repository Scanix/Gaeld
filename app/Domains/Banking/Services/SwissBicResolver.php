<?php

declare(strict_types=1);

namespace App\Domains\Banking\Services;

/**
 * Resolves a SWIFT/BIC from a Swiss/Liechtenstein IBAN by looking up
 * the SIX Interbank Identification Number (IID — IBAN positions 5-8,
 * stored as a 5-digit zero-padded string).
 *
 * The embedded table is intentionally conservative: it only covers
 * institutions whose BIC is publicly published and stable. Emitting
 * an incorrect BIC would route the payment to the wrong bank, so for
 * unknown IIDs the resolver returns `null` and the UI falls back to
 * the SIX bank-master lookup link.
 *
 * Source: each bank's published SWIFT/BIC plus the SIX Interbank
 * Identification Number directory.
 */
final class SwissBicResolver
{
    /**
     * Exact 5-digit IID → BIC.
     *
     * @var array<string, string>
     */
    private const EXACT = [
        // PostFinance
        '09000' => 'POFICHBEXXX',

        // Cantonal banks
        '00700' => 'ZKBKCHZZ80A', // Zürcher Kantonalbank
        '00761' => 'BEKBCH22XXX', // Berner Kantonalbank
        '00765' => 'KBLUCH22XXX', // Luzerner Kantonalbank
        '00767' => 'BCVLCH2LXXX', // Banque Cantonale Vaudoise
        '00770' => 'KBSGCH22XXX', // St. Galler Kantonalbank
        '00773' => 'KBSOCH22XXX', // Solothurner Kantonalbank
        '00774' => 'KBTGCH22XXX', // Thurgauer Kantonalbank
        '00777' => 'BSCTCH22XXX', // Basler Kantonalbank
        '00778' => 'KBSZCH22XXX', // Schwyzer Kantonalbank
        '00781' => 'GRKBCH2270A', // Graubündner Kantonalbank
        '00782' => 'BCJUCH22XXX', // Banque Cantonale du Jura
        '00783' => 'BCNNCH22XXX', // Banque Cantonale Neuchâteloise
        '00784' => 'BCFRCH22XXX', // Banque Cantonale de Fribourg
        '00785' => 'BCVSCH2LXXX', // Banque Cantonale du Valais
        '00786' => 'GLKBCH22XXX', // Glarner Kantonalbank
        '00787' => 'KBAGCH22XXX', // Aargauische Kantonalbank
        '00788' => 'BCGECHGGXXX', // Banque Cantonale de Genève

        // Migros Bank
        '00840' => 'MIGRCHZZXXX',

        // Bank Cler
        '08440' => 'BCLRCHBBXXX',

        // Valiant Bank
        '06300' => 'VABECH22XXX',

        // Hypothekarbank Lenzburg
        '08390' => 'HYPLCH22XXX',

        // Bank Linth (LLB Group)
        '08731' => 'LINSCH23XXX',

        // Liechtensteinische Landesbank
        '08800' => 'LILALI2XXXX',

        // VP Bank
        '08810' => 'VPBVLI2XXXX',

        // Bank CIC (Switzerland)
        '08704' => 'CIALCHBBXXX',
    ];

    /**
     * Inclusive 5-digit IID ranges that map uniformly to one BIC.
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const RANGES = [
        // UBS (post-CS merger): all UBS branches clear via UBSWCHZH80A.
        ['00200', '00299', 'UBSWCHZH80A'],

        // Credit Suisse (now UBS) — historical IBANs still routable.
        ['04800', '04899', 'CRESCHZZ80A'],

        // Raiffeisen Schweiz — local Raiffeisenbanken clear via the
        // central institution.
        ['80000', '81999', 'RAIFCH22XXX'],
    ];

    /**
     * Resolve a BIC from an IBAN. Returns `null` when the IBAN is not
     * Swiss/Liechtenstein, malformed, or the IID is not in the
     * curated table.
     */
    public function resolveFromIban(?string $iban): ?string
    {
        if ($iban === null) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');

        if (preg_match('/^(CH|LI)\d{19}$/', $normalized) !== 1) {
            return null;
        }

        return $this->resolveFromIid(substr($normalized, 4, 5));
    }

    /**
     * Resolve a BIC directly from a 5-digit IID.
     */
    public function resolveFromIid(string $iid): ?string
    {
        if (preg_match('/^\d{5}$/', $iid) !== 1) {
            return null;
        }

        if (isset(self::EXACT[$iid])) {
            return self::EXACT[$iid];
        }

        foreach (self::RANGES as [$start, $end, $bic]) {
            if ($iid >= $start && $iid <= $end) {
                return $bic;
            }
        }

        return null;
    }
}
