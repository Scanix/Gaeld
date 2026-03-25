<?php

namespace App\Domains\Accounting\ChartTemplates;

use App\Domains\Accounting\Enums\AccountType;

class SwissSmeTemplate implements ChartTemplateInterface
{
    public function key(): string
    {
        return 'swiss_sme';
    }

    public function labelKey(): string
    {
        return 'chart_swiss_sme';
    }

    public function descriptionKey(): string
    {
        return 'chart_swiss_sme_desc';
    }

    public function seedsVatRates(): bool
    {
        return true;
    }

    public function accounts(): array
    {
        return [
            // Class 1: Assets (Aktiven)
            ['code' => '1000', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Cash', 'fr' => 'Caisse', 'de' => 'Kasse', 'it' => 'Cassa', 'rm' => 'Cassa',
            ]],
            ['code' => '1010', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Post Office Account', 'fr' => 'Compte postal', 'de' => 'Postkonto', 'it' => 'Conto postale', 'rm' => 'Quint postal',
            ]],
            ['code' => '1020', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Bank Account CHF', 'fr' => 'Compte bancaire CHF', 'de' => 'Bankkonto CHF', 'it' => 'Conto bancario CHF', 'rm' => 'Quint da banca CHF',
            ]],
            ['code' => '1021', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Bank Account EUR', 'fr' => 'Compte bancaire EUR', 'de' => 'Bankkonto EUR', 'it' => 'Conto bancario EUR', 'rm' => 'Quint da banca EUR',
            ]],
            ['code' => '1100', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Accounts Receivable', 'fr' => 'Débiteurs', 'de' => 'Debitoren', 'it' => 'Debitori', 'rm' => 'Debiturs',
            ]],
            ['code' => '1109', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Allowance for Doubtful Accounts', 'fr' => 'Provision pour créances douteuses', 'de' => 'Delkredere', 'it' => 'Delcredere', 'rm' => 'Delcredere',
            ]],
            ['code' => '1170', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'VAT Input Tax (Vorsteuer)', 'fr' => 'Impôt préalable (TVA)', 'de' => 'Vorsteuer (MWST)', 'it' => 'Imposta precedente (IVA)', 'rm' => 'Tagl sin la valur agiunschida precedenta',
            ]],
            ['code' => '1200', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Inventory', 'fr' => 'Stocks', 'de' => 'Vorräte', 'it' => 'Scorte', 'rm' => 'Reservas',
            ]],
            ['code' => '1300', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Prepaid Expenses', 'fr' => 'Charges payées d\'avance', 'de' => 'Aktive Rechnungsabgrenzung', 'it' => 'Risconti attivi', 'rm' => 'Delimitaziun activa',
            ]],
            ['code' => '1500', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Machinery and Equipment', 'fr' => 'Machines et équipements', 'de' => 'Maschinen und Apparate', 'it' => 'Macchine e impianti', 'rm' => 'Maschinas ed apparats',
            ]],
            ['code' => '1510', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Office Equipment', 'fr' => 'Mobilier de bureau', 'de' => 'Büromobiliar', 'it' => 'Mobili d\'ufficio', 'rm' => 'Mobigliar da biro',
            ]],
            ['code' => '1520', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'IT Equipment', 'fr' => 'Équipement informatique', 'de' => 'Informatik', 'it' => 'Attrezzatura informatica', 'rm' => 'Informatica',
            ]],
            ['code' => '1530', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Vehicles', 'fr' => 'Véhicules', 'de' => 'Fahrzeuge', 'it' => 'Veicoli', 'rm' => 'Vehichels',
            ]],
            ['code' => '1540', 'type' => AccountType::Asset->value, 'name' => [
                'en' => 'Tools', 'fr' => 'Outillage', 'de' => 'Werkzeuge', 'it' => 'Utensili', 'rm' => 'Utensils',
            ]],

            // Class 2: Liabilities (Passiven)
            ['code' => '2000', 'type' => AccountType::Liability->value, 'name' => [
                'en' => 'Accounts Payable', 'fr' => 'Créanciers', 'de' => 'Kreditoren', 'it' => 'Creditori', 'rm' => 'Crediturs',
            ]],
            ['code' => '2100', 'type' => AccountType::Liability->value, 'name' => [
                'en' => 'Bank Loan Short-term', 'fr' => 'Emprunt bancaire court terme', 'de' => 'Bankdarlehen kurzfristig', 'it' => 'Prestito bancario a breve termine', 'rm' => 'Imprest da banca a curt termin',
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
            ['code' => '2400', 'type' => AccountType::Liability->value, 'name' => [
                'en' => 'Bank Loan Long-term', 'fr' => 'Emprunt bancaire long terme', 'de' => 'Bankdarlehen langfristig', 'it' => 'Prestito bancario a lungo termine', 'rm' => 'Imprest da banca a lung termin',
            ]],

            // Class 2.8: Equity (Eigenkapital)
            ['code' => '2800', 'type' => AccountType::Equity->value, 'name' => [
                'en' => 'Share Capital', 'fr' => 'Capital social', 'de' => 'Aktienkapital', 'it' => 'Capitale sociale', 'rm' => 'Chapital d\'aczias',
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

            // Class 3: Revenue (Ertrag)
            ['code' => '3000', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Revenue from Services', 'fr' => 'Produits des prestations de services', 'de' => 'Dienstleistungserlöse', 'it' => 'Ricavi da prestazioni di servizi', 'rm' => 'Entradas da prestaziuns da servetschs',
            ]],
            ['code' => '3200', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Revenue from Products', 'fr' => 'Produits des ventes de marchandises', 'de' => 'Handelserlöse', 'it' => 'Ricavi da vendite di merci', 'rm' => 'Entradas da venditas da products',
            ]],
            ['code' => '3400', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Other Revenue', 'fr' => 'Autres produits', 'de' => 'Übrige Erlöse', 'it' => 'Altri ricavi', 'rm' => 'Autras entradas',
            ]],
            ['code' => '3800', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Discounts Given', 'fr' => 'Escomptes accordés', 'de' => 'Skonti', 'it' => 'Sconti concessi', 'rm' => 'Rabats concedids',
            ]],
            ['code' => '3900', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Revenue Corrections', 'fr' => 'Corrections de produits', 'de' => 'Erlösberichtigungen', 'it' => 'Rettifiche di ricavi', 'rm' => 'Correcturas d\'entradas',
            ]],

            // Class 4: Cost of Goods/Services (Aufwand für Material und Dienstleistungen)
            ['code' => '4000', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Cost of Materials', 'fr' => 'Charges de matières', 'de' => 'Materialaufwand', 'it' => 'Costi per materiale', 'rm' => 'Custs da material',
            ]],
            ['code' => '4200', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Cost of Services', 'fr' => 'Charges de prestations de tiers', 'de' => 'Aufwand für Drittleistungen', 'it' => 'Costi per servizi di terzi', 'rm' => 'Custs da prestaziuns da terzas',
            ]],
            ['code' => '4400', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Subcontractor Costs', 'fr' => 'Charges de sous-traitance', 'de' => 'Subunternehmerkosten', 'it' => 'Costi di subappalto', 'rm' => 'Custs da subentreprenda',
            ]],

            // Class 5: Personnel Expenses (Personalaufwand)
            ['code' => '5000', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Salaries', 'fr' => 'Salaires', 'de' => 'Löhne', 'it' => 'Salari', 'rm' => 'Salaris',
            ]],
            ['code' => '5700', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Social Security Contributions', 'fr' => 'Charges sociales', 'de' => 'Sozialversicherungsaufwand', 'it' => 'Contributi sociali', 'rm' => 'Contribuziuns socialas',
            ]],
            ['code' => '5800', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Other Personnel Expenses', 'fr' => 'Autres charges de personnel', 'de' => 'Übriger Personalaufwand', 'it' => 'Altre spese del personale', 'rm' => 'Autras expensas da persunal',
            ]],
            ['code' => '5900', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Temporary Staff', 'fr' => 'Personnel temporaire', 'de' => 'Temporärpersonal', 'it' => 'Personale temporaneo', 'rm' => 'Persunal temporar',
            ]],

            // Class 6: Other Operating Expenses (Übriger betrieblicher Aufwand)
            ['code' => '6000', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Rent', 'fr' => 'Loyer', 'de' => 'Miete', 'it' => 'Affitto', 'rm' => 'Tschains',
            ]],
            ['code' => '6100', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Maintenance and Repairs', 'fr' => 'Entretien et réparations', 'de' => 'Unterhalt und Reparaturen', 'it' => 'Manutenzione e riparazioni', 'rm' => 'Manteniment e reparaturas',
            ]],
            ['code' => '6200', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Vehicle Expenses', 'fr' => 'Charges de véhicules', 'de' => 'Fahrzeugaufwand', 'it' => 'Spese per veicoli', 'rm' => 'Expensas da vehichels',
            ]],
            ['code' => '6300', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Insurance', 'fr' => 'Assurances', 'de' => 'Versicherungen', 'it' => 'Assicurazioni', 'rm' => 'Assicuranzas',
            ]],
            ['code' => '6400', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Energy and Utilities', 'fr' => 'Énergie et charges', 'de' => 'Energie und Nebenkosten', 'it' => 'Energia e utenze', 'rm' => 'Energia e custs accessorics',
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
            ['code' => '6900', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Financial Expenses', 'fr' => 'Charges financières', 'de' => 'Finanzaufwand', 'it' => 'Oneri finanziari', 'rm' => 'Expensas finanzialas',
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
            ['code' => '8000', 'type' => AccountType::Revenue->value, 'name' => [
                'en' => 'Extraordinary Revenue', 'fr' => 'Produits exceptionnels', 'de' => 'Ausserordentliche Erträge', 'it' => 'Ricavi straordinari', 'rm' => 'Entradas extraordinarias',
            ]],
            ['code' => '8500', 'type' => AccountType::Expense->value, 'name' => [
                'en' => 'Extraordinary Expenses', 'fr' => 'Charges exceptionnelles', 'de' => 'Ausserordentlicher Aufwand', 'it' => 'Costi straordinari', 'rm' => 'Expensas extraordinarias',
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
