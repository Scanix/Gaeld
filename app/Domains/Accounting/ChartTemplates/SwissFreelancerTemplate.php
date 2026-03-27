<?php

namespace App\Domains\Accounting\ChartTemplates;

use App\Domains\Accounting\Enums\AccountType;

/**
 * Simplified Swiss chart of accounts for freelancers and sole proprietors (Einzelfirma).
 * Derived from the Swiss SME Kontenrahmen KMU, stripped down to essential accounts.
 */
class SwissFreelancerTemplate implements ChartTemplateInterface
{
    public function key(): string
    {
        return 'swiss_freelancer';
    }

    public function labelKey(): string
    {
        return 'chart_swiss_freelancer';
    }

    public function descriptionKey(): string
    {
        return 'chart_swiss_freelancer_desc';
    }

    public function seedsVatRates(): bool
    {
        return true;
    }

    public function accounts(): array
    {
        return [
            // Class 1: Assets
            ['code' => '1000', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Cash', 'fr' => 'Caisse', 'de' => 'Kasse', 'it' => 'Cassa', 'rm' => 'Cassa',
            ]],
            ['code' => '1020', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Bank Account CHF', 'fr' => 'Compte bancaire CHF', 'de' => 'Bankkonto CHF', 'it' => 'Conto bancario CHF', 'rm' => 'Quint da banca CHF',
            ]],
            ['code' => '1100', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Accounts Receivable', 'fr' => 'Débiteurs', 'de' => 'Debitoren', 'it' => 'Debitori', 'rm' => 'Debiturs',
            ]],
            ['code' => '1170', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'VAT Input Tax (Vorsteuer)', 'fr' => 'Impôt préalable (TVA)', 'de' => 'Vorsteuer (MWST)', 'it' => 'Imposta precedente (IVA)', 'rm' => 'Tagl sin la valur agiunschida precedenta',
            ]],
            ['code' => '1300', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Prepaid Expenses', 'fr' => 'Charges payées d\'avance', 'de' => 'Aktive Rechnungsabgrenzung', 'it' => 'Risconti attivi', 'rm' => 'Delimitaziun activa',
            ]],
            ['code' => '1520', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'IT Equipment', 'fr' => 'Équipement informatique', 'de' => 'Informatik', 'it' => 'Attrezzatura informatica', 'rm' => 'Informatica',
            ]],

            // Class 2: Liabilities
            ['code' => '2000', 'type' => AccountType::Liability->value, 'name' => [
                'en' => 'Accounts Payable', 'fr' => 'Créanciers', 'de' => 'Kreditoren', 'it' => 'Creditori', 'rm' => 'Crediturs',
            ]],
            ['code' => '2200', 'type' => AccountType::Liability->value, 'name' => [
                'en' => 'VAT Output Tax (Umsatzsteuer)', 'fr' => 'TVA due', 'de' => 'Umsatzsteuer (MWST)', 'it' => 'IVA dovuta', 'rm' => 'Tagl sin la cifra d\'affars',
            ]],
            ['code' => '2201', 'type' => AccountType::Liability->value, 'name' => [
                'en' => 'VAT Payable', 'fr' => 'TVA à payer', 'de' => 'MWST-Zahllast', 'it' => 'IVA da pagare', 'rm' => 'TIVA da pajar',
            ]],
            ['code' => '2270', 'type' => AccountType::Liability->value, 'name' => [
                'en' => 'Social Security Payable', 'fr' => 'Assurances sociales à payer', 'de' => 'Sozialversicherungen', 'it' => 'Assicurazioni sociali da pagare', 'rm' => 'Assicuranzas socialas da pajar',
            ]],
            ['code' => '2300', 'type' => AccountType::Liability->value, 'name' => [
                'en' => 'Accrued Liabilities', 'fr' => 'Charges à payer', 'de' => 'Passive Rechnungsabgrenzung', 'it' => 'Risconti passivi', 'rm' => 'Delimitaziun passiva',
            ]],

            // Class 2.8: Equity (no share capital for sole proprietors)
            ['code' => '2800', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Owner Equity', 'fr' => 'Capital propre', 'de' => 'Eigenkapital', 'it' => 'Capitale proprio', 'rm' => 'Chapital agen',
            ]],
            ['code' => '2900', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Retained Earnings', 'fr' => 'Bénéfice reporté', 'de' => 'Gewinnvortrag', 'it' => 'Utile riportato', 'rm' => 'Gudogn reportà',
            ]],
            ['code' => '2950', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Current Year Profit/Loss', 'fr' => 'Résultat de l\'exercice', 'de' => 'Jahresergebnis', 'it' => 'Risultato d\'esercizio', 'rm' => 'Resultat da l\'onn',
            ]],
            ['code' => '2970', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Owner Drawings', 'fr' => 'Prélèvements privés', 'de' => 'Privatbezüge', 'it' => 'Prelevamenti privati', 'rm' => 'Retratgas privatas',
            ]],

            // Class 3: Revenue
            ['code' => '3000', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Revenue from Services', 'fr' => 'Produits des prestations de services', 'de' => 'Dienstleistungserlöse', 'it' => 'Ricavi da prestazioni di servizi', 'rm' => 'Entradas da prestaziuns da servetschs',
            ]],
            ['code' => '3400', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Other Revenue', 'fr' => 'Autres produits', 'de' => 'Übrige Erlöse', 'it' => 'Altri ricavi', 'rm' => 'Autras entradas',
            ]],
            ['code' => '3900', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Revenue Corrections', 'fr' => 'Corrections de produits', 'de' => 'Erlösberichtigungen', 'it' => 'Rettifiche di ricavi', 'rm' => 'Correcturas d\'entradas',
            ]],

            // Class 4-5: Simplified expenses (no subcontractors, no temp staff)
            ['code' => '4200', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Cost of Services', 'fr' => 'Charges de prestations de tiers', 'de' => 'Aufwand für Drittleistungen', 'it' => 'Costi per servizi di terzi', 'rm' => 'Custs da prestaziuns da terzas',
            ]],
            ['code' => '5000', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Salaries', 'fr' => 'Salaires', 'de' => 'Löhne', 'it' => 'Salari', 'rm' => 'Salaris',
            ]],
            ['code' => '5700', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Social Security Contributions', 'fr' => 'Charges sociales', 'de' => 'Sozialversicherungsaufwand', 'it' => 'Contributi sociali', 'rm' => 'Contribuziuns socialas',
            ]],

            // Class 6: Operating Expenses (essential only)
            ['code' => '6000', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Rent', 'fr' => 'Loyer', 'de' => 'Miete', 'it' => 'Affitto', 'rm' => 'Tschains',
            ]],
            ['code' => '6300', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Insurance', 'fr' => 'Assurances', 'de' => 'Versicherungen', 'it' => 'Assicurazioni', 'rm' => 'Assicuranzas',
            ]],
            ['code' => '6500', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Office Supplies', 'fr' => 'Fournitures de bureau', 'de' => 'Büromaterial', 'it' => 'Materiale d\'ufficio', 'rm' => 'Material da biro',
            ]],
            ['code' => '6510', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Telephone and Internet', 'fr' => 'Téléphone et Internet', 'de' => 'Telefon und Internet', 'it' => 'Telefono e Internet', 'rm' => 'Telefon ed Internet',
            ]],
            ['code' => '6530', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Software and Subscriptions', 'fr' => 'Logiciels et abonnements', 'de' => 'Software und Abonnements', 'it' => 'Software e abbonamenti', 'rm' => 'Software e abunaments',
            ]],
            ['code' => '6570', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Accounting and Legal Fees', 'fr' => 'Honoraires comptables et juridiques', 'de' => 'Buchhaltungs- und Rechtskosten', 'it' => 'Spese contabili e legali', 'rm' => 'Custs da contabilitad e dretgira',
            ]],
            ['code' => '6600', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Advertising and Marketing', 'fr' => 'Publicité et marketing', 'de' => 'Werbung und Marketing', 'it' => 'Pubblicità e marketing', 'rm' => 'Reclama e marketing',
            ]],
            ['code' => '6700', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Travel Expenses', 'fr' => 'Frais de déplacement', 'de' => 'Reisekosten', 'it' => 'Spese di viaggio', 'rm' => 'Custs da viadi',
            ]],
            ['code' => '6800', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Depreciation', 'fr' => 'Amortissements', 'de' => 'Abschreibungen', 'it' => 'Ammortamenti', 'rm' => 'Amortisaziuns',
            ]],
            ['code' => '6950', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Bank Fees', 'fr' => 'Frais bancaires', 'de' => 'Bankspesen', 'it' => 'Spese bancarie', 'rm' => 'Spesas bancaras',
            ]],

            // Class 9: Closing
            ['code' => '9000', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Opening Balance', 'fr' => 'Bilan d\'ouverture', 'de' => 'Eröffnungsbilanz', 'it' => 'Bilancio di apertura', 'rm' => 'Bilantscha d\'avertura',
            ]],
            ['code' => '9100', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Profit and Loss Summary', 'fr' => 'Résultat (compte de résultat)', 'de' => 'Erfolgsrechnung', 'it' => 'Conto economico', 'rm' => 'Quint da success',
            ]],
        ];
    }
}
