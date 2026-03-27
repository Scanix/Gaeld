<?php

namespace App\Domains\Accounting\ChartTemplates;

use App\Domains\Accounting\Enums\AccountType;

/**
 * Swiss chart of accounts tailored for associations (Verein).
 * Based on the Swiss Kontenrahmen KMU, adapted with membership fees,
 * donations, grants, and event-specific accounts.
 */
class SwissAssociationTemplate implements ChartTemplateInterface
{
    public function key(): string
    {
        return 'swiss_association';
    }

    public function labelKey(): string
    {
        return 'chart_swiss_association';
    }

    public function descriptionKey(): string
    {
        return 'chart_swiss_association_desc';
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
            ['code' => '1010', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Post Office Account', 'fr' => 'Compte postal', 'de' => 'Postkonto', 'it' => 'Conto postale', 'rm' => 'Quint postal',
            ]],
            ['code' => '1020', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Bank Account CHF', 'fr' => 'Compte bancaire CHF', 'de' => 'Bankkonto CHF', 'it' => 'Conto bancario CHF', 'rm' => 'Quint da banca CHF',
            ]],
            ['code' => '1100', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Membership Fees Receivable', 'fr' => 'Cotisations à recevoir', 'de' => 'Ausstehende Mitgliederbeiträge', 'it' => 'Quote associative da incassare', 'rm' => 'Contribuziuns da members da retschaiver',
            ]],
            ['code' => '1170', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'VAT Input Tax (Vorsteuer)', 'fr' => 'Impôt préalable (TVA)', 'de' => 'Vorsteuer (MWST)', 'it' => 'Imposta precedente (IVA)', 'rm' => 'Tagl sin la valur agiunschida precedenta',
            ]],
            ['code' => '1300', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Prepaid Expenses', 'fr' => 'Charges payées d\'avance', 'de' => 'Aktive Rechnungsabgrenzung', 'it' => 'Risconti attivi', 'rm' => 'Delimitaziun activa',
            ]],
            ['code' => '1510', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Equipment', 'fr' => 'Mobilier et équipements', 'de' => 'Mobiliar und Einrichtungen', 'it' => 'Mobili e attrezzature', 'rm' => 'Mobigliar ed equipaments',
            ]],

            // Class 2: Liabilities
            ['code' => '2000', 'type' => AccountType::Liability->value, 'name' => [
                'en' => 'Accounts Payable', 'fr' => 'Créanciers', 'de' => 'Kreditoren', 'it' => 'Creditori', 'rm' => 'Crediturs',
            ]],
            ['code' => '2200', 'type' => AccountType::Liability->value, 'name' => [
                'en' => 'VAT Output Tax (Umsatzsteuer)', 'fr' => 'TVA due', 'de' => 'Umsatzsteuer (MWST)', 'it' => 'IVA dovuta', 'rm' => 'Tagl sin la cifra d\'affars',
            ]],
            ['code' => '2300', 'type' => AccountType::Liability->value, 'name' => [
                'en' => 'Accrued Liabilities', 'fr' => 'Charges à payer', 'de' => 'Passive Rechnungsabgrenzung', 'it' => 'Risconti passivi', 'rm' => 'Delimitaziun passiva',
            ]],
            ['code' => '2330', 'type' => AccountType::Liability->value, 'name' => [
                'en' => 'Prepaid Membership Fees', 'fr' => 'Cotisations perçues d\'avance', 'de' => 'Vorausbezahlte Mitgliederbeiträge', 'it' => 'Quote associative incassate in anticipo', 'rm' => 'Contribuziuns da members pajadas en avans',
            ]],

            // Class 2.8: Equity (association-specific)
            ['code' => '2800', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Association Capital', 'fr' => 'Capital de l\'association', 'de' => 'Vereinsvermögen', 'it' => 'Capitale dell\'associazione', 'rm' => 'Chapital da l\'associaziun',
            ]],
            ['code' => '2850', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Restricted Funds', 'fr' => 'Fonds affectés', 'de' => 'Gebundene Fonds', 'it' => 'Fondi vincolati', 'rm' => 'Fonds liads',
            ]],
            ['code' => '2900', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Retained Surplus', 'fr' => 'Excédent reporté', 'de' => 'Vortrag Vorjahr', 'it' => 'Eccedenza riportata', 'rm' => 'Surplis reportà',
            ]],
            ['code' => '2950', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Current Year Surplus/Deficit', 'fr' => 'Résultat de l\'exercice', 'de' => 'Jahresergebnis', 'it' => 'Risultato d\'esercizio', 'rm' => 'Resultat da l\'onn',
            ]],

            // Class 3: Revenue (association-specific)
            ['code' => '3000', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Membership Fees', 'fr' => 'Cotisations des membres', 'de' => 'Mitgliederbeiträge', 'it' => 'Quote associative', 'rm' => 'Contribuziuns da members',
            ]],
            ['code' => '3100', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Donations', 'fr' => 'Dons', 'de' => 'Spenden', 'it' => 'Donazioni', 'rm' => 'Donaziuns',
            ]],
            ['code' => '3200', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Grants and Subsidies', 'fr' => 'Subventions', 'de' => 'Subventionen und Beiträge', 'it' => 'Sovvenzioni e contributi', 'rm' => 'Subvenziuns e contribuziuns',
            ]],
            ['code' => '3300', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Event Revenue', 'fr' => 'Recettes d\'événements', 'de' => 'Veranstaltungserlöse', 'it' => 'Ricavi da eventi', 'rm' => 'Entradas dad occurrenzas',
            ]],
            ['code' => '3400', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Sponsorship Revenue', 'fr' => 'Recettes de sponsoring', 'de' => 'Sponsoringeinnahmen', 'it' => 'Ricavi da sponsorizzazioni', 'rm' => 'Entradas da sponsoring',
            ]],
            ['code' => '3800', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Other Revenue', 'fr' => 'Autres produits', 'de' => 'Übrige Erlöse', 'it' => 'Altri ricavi', 'rm' => 'Autras entradas',
            ]],
            ['code' => '3900', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Revenue Corrections', 'fr' => 'Corrections de produits', 'de' => 'Erlösberichtigungen', 'it' => 'Rettifiche di ricavi', 'rm' => 'Correcturas d\'entradas',
            ]],

            // Class 4-5: Expenses (association-specific)
            ['code' => '4000', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Event Costs', 'fr' => 'Charges d\'événements', 'de' => 'Veranstaltungskosten', 'it' => 'Costi per eventi', 'rm' => 'Custs dad occurrenzas',
            ]],
            ['code' => '4200', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Project Costs', 'fr' => 'Charges de projets', 'de' => 'Projektkosten', 'it' => 'Costi di progetto', 'rm' => 'Custs da projects',
            ]],
            ['code' => '5000', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Salaries', 'fr' => 'Salaires', 'de' => 'Löhne', 'it' => 'Salari', 'rm' => 'Salaris',
            ]],
            ['code' => '5700', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Social Security Contributions', 'fr' => 'Charges sociales', 'de' => 'Sozialversicherungsaufwand', 'it' => 'Contributi sociali', 'rm' => 'Contribuziuns socialas',
            ]],

            // Class 6: Operating Expenses
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
            ['code' => '6520', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Postage and Shipping', 'fr' => 'Frais postaux et d\'expédition', 'de' => 'Porto und Versand', 'it' => 'Spese postali e di spedizione', 'rm' => 'Porto e spediziun',
            ]],
            ['code' => '6600', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Communication and Marketing', 'fr' => 'Communication et marketing', 'de' => 'Kommunikation und Werbung', 'it' => 'Comunicazione e marketing', 'rm' => 'Communicaziun e marketing',
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

            // Class 7-8: Non-operating
            ['code' => '7000', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Non-operating Revenue', 'fr' => 'Produits hors exploitation', 'de' => 'Betriebsfremde Erträge', 'it' => 'Ricavi non operativi', 'rm' => 'Entradas betg operativas',
            ]],
            ['code' => '7500', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Non-operating Expenses', 'fr' => 'Charges hors exploitation', 'de' => 'Betriebsfremder Aufwand', 'it' => 'Costi non operativi', 'rm' => 'Expensas betg operativas',
            ]],

            // Class 9: Closing
            ['code' => '9000', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Opening Balance', 'fr' => 'Bilan d\'ouverture', 'de' => 'Eröffnungsbilanz', 'it' => 'Bilancio di apertura', 'rm' => 'Bilantscha d\'avertura',
            ]],
            ['code' => '9100', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Surplus/Deficit Summary', 'fr' => 'Résultat (compte de résultat)', 'de' => 'Erfolgsrechnung', 'it' => 'Conto economico', 'rm' => 'Quint da success',
            ]],
        ];
    }
}
