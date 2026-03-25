<?php

namespace Database\Seeders;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\BankingService;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Expenses\Actions\ApproveExpenseAction;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;
use App\Domains\Invoicing\Services\InvoiceService;
use App\Domains\Organizations\Enums\Role;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Scout\ModelObserver;
use Spatie\Permission\PermissionRegistrar;

/**
 * Showcase seeder — 3 years of realistic data in 4 languages (de/fr/it/en).
 *
 * Creates one organization per language, each with localized company names,
 * customer names, expense descriptions, invoice line descriptions, etc.
 * Switch organization in the UI to get screenshots in the desired language.
 *
 * Period: January 2024 → March 2026
 *
 * Usage:
 *   php artisan db:seed --class=ShowcaseDataSeeder
 */
class ShowcaseDataSeeder extends Seeder
{
    public function __construct(
        private readonly FinalizeInvoiceAction $finalizeInvoice,
        private readonly ApproveExpenseAction $approveExpense,
        private readonly PostExpenseAction $postExpense,
        private readonly BankingService $bankingService,
        private readonly InvoiceService $invoiceService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  Localized data packs
    // ──────────────────────────────────────────────────────────────

    private function orgDefinitions(): array
    {
        return [
            'de' => [
                'org' => ['name' => 'PixelCraft GmbH', 'legal_name' => 'PixelCraft GmbH', 'address' => 'Pilatusstrasse 22', 'city' => 'Luzern', 'postal_code' => '6003', 'canton' => 'LU', 'vat_number' => 'CHE-345.678.901 MWST'],
                'prefix' => 'PC',
                'bank' => ['iban' => 'CH39 0900 0000 1556 7890 1', 'name' => 'Geschäftskonto', 'bank_name' => 'Luzerner Kantonalbank'],
                'users' => [
                    ['email' => 'sophie@pixelcraft.ch', 'name' => 'Sophie Brunner',  'role' => 'owner'],
                    ['email' => 'thomas@pixelcraft.ch', 'name' => 'Thomas Keller',   'role' => 'member'],
                    ['email' => 'leila@pixelcraft.ch',  'name' => 'Leila Ammann',    'role' => 'member'],
                ],
                'customers' => [
                    ['name' => 'BergBau Immobilien AG',   'email' => 'kontakt@bergbau-immo.ch',    'address' => 'Seestrasse 45',       'city' => 'Luzern',      'postal_code' => '6004', 'phone' => '+41 41 320 12 34', 'vat_number' => 'CHE-111.222.333 MWST', 'payment_terms' => 'Net 30'],
                    ['name' => 'Alpenkäserei Bucher KlG', 'email' => 'info@alpenkaeserei.ch',      'address' => 'Dorfstrasse 8',        'city' => 'Entlebuch',   'postal_code' => '6162', 'phone' => '+41 41 480 55 66'],
                    ['name' => 'Klinik Sonnenberg',       'email' => 'it@klinik-sonnenberg.ch',    'address' => 'Sonnenbergstrasse 12', 'city' => 'Kriens',      'postal_code' => '6010', 'phone' => '+41 41 329 88 00', 'vat_number' => 'CHE-444.555.666 MWST', 'payment_terms' => 'Net 45'],
                    ['name' => 'Luzern Tourismus',        'email' => 'digital@luzern-tourismus.ch','address' => 'Zentralstrasse 5',     'city' => 'Luzern',      'postal_code' => '6003', 'phone' => '+41 41 227 17 17'],
                    ['name' => 'Rüegg Schreinerei GmbH', 'email' => 'info@rueegg-schreinerei.ch', 'address' => 'Industriestrasse 20',  'city' => 'Emmenbrücke', 'postal_code' => '6020', 'phone' => '+41 41 260 33 44'],
                    ['name' => 'SwissFinServ AG',         'email' => 'projekte@swissfinserv.ch',   'address' => 'Alpenquai 30',         'city' => 'Luzern',      'postal_code' => '6005', 'phone' => '+41 41 368 90 00', 'vat_number' => 'CHE-777.888.999 MWST', 'payment_terms' => 'Net 30'],
                    ['name' => 'Velostation Brunner',     'email' => 'hallo@velostation-brunner.ch','address' => 'Baselstrasse 2',      'city' => 'Luzern',      'postal_code' => '6003', 'phone' => '+41 41 210 67 89'],
                ],
                'suppliers' => [
                    ['name' => 'Immobilien Luzern AG', 'email' => 'verwaltung@immo-luzern.ch',  'default_expense_category' => 'Rent'],
                    ['name' => 'Swisscom AG',          'email' => 'business@swisscom.ch',       'default_expense_category' => 'Telephone and Internet'],
                    ['name' => 'Digitec Galaxus AG',   'email' => 'business@digitec.ch',        'default_expense_category' => 'IT Equipment'],
                    ['name' => 'Office World',         'email' => 'service@officeworld.ch',     'default_expense_category' => 'Office Supplies'],
                ],
                'monthlyExpenses' => [
                    ['category' => 'Rent',                       'desc' => 'Büromiete Pilatusstrasse 22',     'amount' => 1800, 'vendor' => 'Immobilien Luzern AG',  'account' => '6000'],
                    ['category' => 'Telephone and Internet',     'desc' => 'Swisscom Internet & Telefonie',   'amount' => 129,  'vendor' => 'Swisscom AG',           'account' => '6510'],
                    ['category' => 'Software and Subscriptions', 'desc' => 'Microsoft 365 Business',          'amount' => 66,   'vendor' => 'Microsoft Ireland',     'account' => '6530'],
                    ['category' => 'Software and Subscriptions', 'desc' => 'GitHub Team',                     'amount' => 38,   'vendor' => 'GitHub Inc.',           'account' => '6530'],
                    ['category' => 'Insurance',                  'desc' => 'Betriebshaftpflichtversicherung', 'amount' => 185,  'vendor' => 'Mobiliar',              'account' => '6300'],
                ],
                'quarterlyExpenses' => [
                    ['category' => 'Accounting and Legal Fees', 'desc' => 'Buchhaltungsberatung',   'amount' => 650, 'vendor' => 'Treuhand Müller & Co.', 'account' => '6570'],
                    ['category' => 'Advertising and Marketing', 'desc' => 'Google Ads Kampagne',    'amount' => 450, 'vendor' => 'Google Ireland Ltd.',   'account' => '6600'],
                ],
                'projects' => [
                    ['cust' => 0, 'desc' => 'Website-Redesign',              'rate' => 165, 'hMin' => 20, 'hMax' => 60],
                    ['cust' => 0, 'desc' => 'SEO-Optimierung & Analytics',   'rate' => 145, 'hMin' => 8,  'hMax' => 20],
                    ['cust' => 0, 'desc' => 'Immobilien-Portal Entwicklung', 'rate' => 180, 'hMin' => 40, 'hMax' => 80],
                    ['cust' => 1, 'desc' => 'Webshop-Integration',           'rate' => 160, 'hMin' => 15, 'hMax' => 40],
                    ['cust' => 1, 'desc' => 'Newsletter-System',             'rate' => 140, 'hMin' => 5,  'hMax' => 15],
                    ['cust' => 2, 'desc' => 'Patientenportal MVP',           'rate' => 185, 'hMin' => 30, 'hMax' => 80],
                    ['cust' => 2, 'desc' => 'Terminbuchungs-Modul',          'rate' => 175, 'hMin' => 20, 'hMax' => 50],
                    ['cust' => 3, 'desc' => 'Event-Kalender App',            'rate' => 170, 'hMin' => 25, 'hMax' => 60],
                    ['cust' => 3, 'desc' => 'Mehrsprachige Website',         'rate' => 155, 'hMin' => 30, 'hMax' => 70],
                    ['cust' => 4, 'desc' => 'Digitalisierung Auftragserfassung', 'rate' => 150, 'hMin' => 15, 'hMax' => 35],
                    ['cust' => 5, 'desc' => 'Compliance Dashboard',          'rate' => 200, 'hMin' => 40, 'hMax' => 100],
                    ['cust' => 5, 'desc' => 'KYC-Workflow Automatisierung',  'rate' => 195, 'hMin' => 30, 'hMax' => 80],
                    ['cust' => 6, 'desc' => 'Online-Buchungsplattform',      'rate' => 155, 'hMin' => 20, 'hMax' => 45],
                ],
                'extraLines' => ['Hosting & Wartung', 'Support-Pauschale', 'Domain-Registrierung', 'SSL-Zertifikat', 'E-Mail Hosting', 'Backup-Service'],
                'salaries' => [
                    ['desc' => 'Lohn Sophie Brunner',  'gross' => 7500, 'social' => 975],
                    ['desc' => 'Lohn Thomas Keller',   'gross' => 7000, 'social' => 910],
                    ['desc' => 'Lohn Leila Ammann',    'gross' => 6800, 'social' => 884],
                ],
                'salaryLabel'  => 'Sozialversicherungsbeiträge',
                'occasionalExpenses' => [
                    ['month' => '2024-01', 'category' => 'IT Equipment',              'desc' => 'MacBook Pro 14" M3 — Arbeitsplatz Sophie',   'amount' => 2899, 'vendor' => 'Apple Store',            'account' => '1520'],
                    ['month' => '2024-02', 'category' => 'Office Supplies',            'desc' => 'Bürostühle (3×)',                              'amount' => 1470, 'vendor' => 'IKEA',                   'account' => '1510'],
                    ['month' => '2024-03', 'category' => 'Software and Subscriptions', 'desc' => 'Figma Professional (Jahreslizenz)',            'amount' => 540,  'vendor' => 'Figma Inc.',             'account' => '6530'],
                    ['month' => '2024-05', 'category' => 'Office Supplies',            'desc' => 'Monitore Dell 27" (2×)',                       'amount' => 980,  'vendor' => 'Digitec Galaxus AG',     'account' => '1510'],
                    ['month' => '2024-07', 'category' => 'Travel Expenses',            'desc' => 'Halbtax-Abonnement Thomas',                   'amount' => 185,  'vendor' => 'SBB CFF FFS',            'account' => '6700'],
                    ['month' => '2024-09', 'category' => 'Professional Services',      'desc' => 'Notarielle Beglaubigung Vertragsänderung',    'amount' => 350,  'vendor' => 'Notariat Luzern',        'account' => '6570'],
                    ['month' => '2024-11', 'category' => 'Advertising and Marketing',  'desc' => 'Druckerei — Visitenkarten & Flyer',           'amount' => 420,  'vendor' => 'Vögeli AG Druckzentrum', 'account' => '6600'],
                    ['month' => '2025-01', 'category' => 'IT Equipment',               'desc' => 'NAS Synology DS923+ + Festplatten',           'amount' => 1350, 'vendor' => 'Digitec Galaxus AG',     'account' => '1520'],
                    ['month' => '2025-03', 'category' => 'Software and Subscriptions', 'desc' => 'JetBrains All Products Pack (Jahreslizenz)',   'amount' => 649,  'vendor' => 'JetBrains s.r.o.',       'account' => '6530'],
                    ['month' => '2025-04', 'category' => 'Travel Expenses',            'desc' => 'Konferenz WordCamp Zürich — 2 Tickets',       'amount' => 390,  'vendor' => 'WordCamp Schweiz',       'account' => '6700'],
                    ['month' => '2025-06', 'category' => 'IT Equipment',               'desc' => 'MacBook Air M3 — Arbeitsplatz Leila',         'amount' => 1599, 'vendor' => 'Apple Store',            'account' => '1520'],
                    ['month' => '2025-08', 'category' => 'Office Supplies',            'desc' => 'Druckerpapier + Toner (Halbjahresbedarf)',    'amount' => 180,  'vendor' => 'Office World',           'account' => '6500'],
                    ['month' => '2025-10', 'category' => 'Professional Services',      'desc' => 'Steuerberatung — Jahresabschluss 2024',       'amount' => 1200, 'vendor' => 'Treuhand Müller & Co.',   'account' => '6570'],
                    ['month' => '2025-12', 'category' => 'Advertising and Marketing',  'desc' => 'Weihnachtskarten & Kundengeschenke',          'amount' => 340,  'vendor' => 'Bucherer AG',            'account' => '6600'],
                    ['month' => '2026-01', 'category' => 'IT Equipment',               'desc' => 'MacBook Pro 16" M4 — Arbeitsplatz Thomas',    'amount' => 3299, 'vendor' => 'Apple Store',            'account' => '1520'],
                    ['month' => '2026-02', 'category' => 'Software and Subscriptions', 'desc' => 'Notion Team Plan (Jahreslizenz)',              'amount' => 288,  'vendor' => 'Notion Labs Inc.',       'account' => '6530'],
                    ['month' => '2026-03', 'category' => 'Travel Expenses',            'desc' => 'Kundenbesuch Bern — SBB + Hotel',              'amount' => 465,  'vendor' => 'SBB CFF FFS',            'account' => '6700'],
                ],
                'invoiceNotes'    => 'Vielen Dank für die Zusammenarbeit.',
                'cancelledNotes'  => 'Annuliert — Projektumfang geändert',
                'partialRef'      => 'Teilzahlung',
                'draftProjects'   => [
                    ['cust' => 2, 'desc' => 'Patientenportal Phase 3 — Entwurf', 'hours' => 35, 'rate' => 185],
                    ['cust' => 5, 'desc' => 'Dashboard Analytics Erweiterung',   'hours' => 20, 'rate' => 200],
                ],
                'pendingExpenses' => [
                    ['category' => 'Travel Expenses', 'desc' => 'SBB-Billett Luzern–Bern (Kundentermin)', 'amount' => 88, 'vendor' => 'SBB CFF FFS'],
                    ['category' => 'Office Supplies', 'desc' => 'Whiteboard + Stifte (Brainstorming-Equipment)', 'amount' => 210, 'vendor' => 'Office World'],
                ],
                'unmatchedBankTx' => [
                    ['desc' => 'Zahlung TWINT — unbekannt',                       'amount' => 150,  'type' => 'credit', 'ref' => 'TWINT-REF-99881'],
                    ['desc' => 'Lastschrift Mobiliar Versicherung — Jahresprämie', 'amount' => 2220, 'type' => 'debit',  'ref' => 'LSV-MOB-2026-Q1'],
                ],
                'paymentLabel' => 'Zahlung',
                'partialLabel' => 'Teilzahlung',
            ],

            'fr' => [
                'org' => ['name' => 'NovaTech Sàrl', 'legal_name' => 'NovaTech Sàrl', 'address' => 'Rue du Mont-Blanc 18', 'city' => 'Genève', 'postal_code' => '1201', 'canton' => 'GE', 'vat_number' => 'CHE-456.789.012 TVA'],
                'prefix' => 'NT',
                'bank' => ['iban' => 'CH56 0483 5012 3456 7800 9', 'name' => 'Compte courant', 'bank_name' => 'Banque Cantonale de Genève'],
                'users' => [
                    ['email' => 'claire@novatech-ge.ch',  'name' => 'Claire Dubois',   'role' => 'owner'],
                    ['email' => 'marc@novatech-ge.ch',    'name' => 'Marc Favre',      'role' => 'member'],
                    ['email' => 'nadia@novatech-ge.ch',   'name' => 'Nadia Berset',    'role' => 'member'],
                ],
                'customers' => [
                    ['name' => 'Genève Événements SA',     'email' => 'contact@ge-events.ch',       'address' => 'Quai du Mont-Blanc 2',    'city' => 'Genève',    'postal_code' => '1201', 'phone' => '+41 22 310 12 34', 'vat_number' => 'CHE-222.333.444 TVA', 'payment_terms' => 'Net 30'],
                    ['name' => 'Fromagerie du Léman',      'email' => 'info@fromagerie-leman.ch',   'address' => 'Route de Lausanne 50',    'city' => 'Nyon',      'postal_code' => '1260', 'phone' => '+41 22 361 55 66'],
                    ['name' => 'Clinique Belle-Rive',      'email' => 'it@clinique-bellerive.ch',   'address' => 'Avenue de la Gare 15',    'city' => 'Lausanne',  'postal_code' => '1003', 'phone' => '+41 21 329 88 00', 'vat_number' => 'CHE-555.666.777 TVA', 'payment_terms' => 'Net 45'],
                    ['name' => 'Office du Tourisme Genève','email' => 'digital@tourisme-geneve.ch', 'address' => 'Rue du Rhône 8',          'city' => 'Genève',    'postal_code' => '1204', 'phone' => '+41 22 909 70 00'],
                    ['name' => 'Menuiserie Rochat & Fils', 'email' => 'info@rochat-menuiserie.ch',  'address' => 'Chemin des Ateliers 4',   'city' => 'Carouge',   'postal_code' => '1227', 'phone' => '+41 22 342 33 44'],
                    ['name' => 'FinanceSwiss SA',          'email' => 'projets@financeswiss.ch',    'address' => 'Place Longemalle 1',      'city' => 'Genève',    'postal_code' => '1204', 'phone' => '+41 22 818 90 00', 'vat_number' => 'CHE-888.999.000 TVA', 'payment_terms' => 'Net 30'],
                    ['name' => 'Cycles du Lac',            'email' => 'contact@cyclesdulac.ch',     'address' => 'Avenue de Frontenex 12',  'city' => 'Genève',    'postal_code' => '1207', 'phone' => '+41 22 735 67 89'],
                ],
                'suppliers' => [
                    ['name' => 'Régie Immobilière Genève',  'email' => 'gestion@regie-ge.ch',        'default_expense_category' => 'Rent'],
                    ['name' => 'Swisscom SA',               'email' => 'entreprise@swisscom.ch',     'default_expense_category' => 'Telephone and Internet'],
                    ['name' => 'Digitec Galaxus SA',        'email' => 'pro@digitec.ch',             'default_expense_category' => 'IT Equipment'],
                    ['name' => 'Lyreco Suisse SA',          'email' => 'service@lyreco.ch',          'default_expense_category' => 'Office Supplies'],
                ],
                'monthlyExpenses' => [
                    ['category' => 'Rent',                       'desc' => 'Loyer bureau Rue du Mont-Blanc 18',       'amount' => 2100, 'vendor' => 'Régie Immobilière Genève', 'account' => '6000'],
                    ['category' => 'Telephone and Internet',     'desc' => 'Swisscom Internet & téléphonie',          'amount' => 139,  'vendor' => 'Swisscom SA',              'account' => '6510'],
                    ['category' => 'Software and Subscriptions', 'desc' => 'Microsoft 365 Business',                  'amount' => 66,   'vendor' => 'Microsoft Ireland',        'account' => '6530'],
                    ['category' => 'Software and Subscriptions', 'desc' => 'GitHub Team',                             'amount' => 38,   'vendor' => 'GitHub Inc.',              'account' => '6530'],
                    ['category' => 'Insurance',                  'desc' => 'Assurance responsabilité professionnelle', 'amount' => 195,  'vendor' => 'Mobilière',                'account' => '6300'],
                ],
                'quarterlyExpenses' => [
                    ['category' => 'Accounting and Legal Fees', 'desc' => 'Conseil comptable trimestriel',    'amount' => 700, 'vendor' => 'Fiduciaire Bonvin SA',  'account' => '6570'],
                    ['category' => 'Advertising and Marketing', 'desc' => 'Campagne Google Ads',              'amount' => 480, 'vendor' => 'Google Ireland Ltd.',   'account' => '6600'],
                ],
                'projects' => [
                    ['cust' => 0, 'desc' => 'Refonte du site web',                'rate' => 170, 'hMin' => 20, 'hMax' => 60],
                    ['cust' => 0, 'desc' => 'Optimisation SEO & analytics',       'rate' => 150, 'hMin' => 8,  'hMax' => 20],
                    ['cust' => 0, 'desc' => 'Portail événementiel',               'rate' => 185, 'hMin' => 40, 'hMax' => 80],
                    ['cust' => 1, 'desc' => 'Intégration boutique en ligne',       'rate' => 165, 'hMin' => 15, 'hMax' => 40],
                    ['cust' => 1, 'desc' => 'Système de newsletter',              'rate' => 145, 'hMin' => 5,  'hMax' => 15],
                    ['cust' => 2, 'desc' => 'Portail patient MVP',                'rate' => 190, 'hMin' => 30, 'hMax' => 80],
                    ['cust' => 2, 'desc' => 'Module de prise de rendez-vous',     'rate' => 180, 'hMin' => 20, 'hMax' => 50],
                    ['cust' => 3, 'desc' => 'Application calendrier événements',  'rate' => 175, 'hMin' => 25, 'hMax' => 60],
                    ['cust' => 3, 'desc' => 'Site web multilingue',               'rate' => 160, 'hMin' => 30, 'hMax' => 70],
                    ['cust' => 4, 'desc' => 'Digitalisation saisie des commandes','rate' => 155, 'hMin' => 15, 'hMax' => 35],
                    ['cust' => 5, 'desc' => 'Tableau de bord conformité',         'rate' => 205, 'hMin' => 40, 'hMax' => 100],
                    ['cust' => 5, 'desc' => 'Automatisation workflow KYC',        'rate' => 200, 'hMin' => 30, 'hMax' => 80],
                    ['cust' => 6, 'desc' => 'Plateforme de réservation en ligne', 'rate' => 160, 'hMin' => 20, 'hMax' => 45],
                ],
                'extraLines' => ['Hébergement & maintenance', 'Forfait support', 'Enregistrement de domaine', 'Certificat SSL', 'Hébergement e-mail', 'Service de sauvegarde'],
                'salaries' => [
                    ['desc' => 'Salaire Claire Dubois', 'gross' => 7800, 'social' => 1014],
                    ['desc' => 'Salaire Marc Favre',    'gross' => 7200, 'social' => 936],
                    ['desc' => 'Salaire Nadia Berset',  'gross' => 7000, 'social' => 910],
                ],
                'salaryLabel'  => 'Cotisations sociales',
                'occasionalExpenses' => [
                    ['month' => '2024-01', 'category' => 'IT Equipment',              'desc' => 'MacBook Pro 14" M3 — poste Claire',           'amount' => 2899, 'vendor' => 'Apple Store',            'account' => '1520'],
                    ['month' => '2024-02', 'category' => 'Office Supplies',            'desc' => 'Chaises de bureau (3×)',                       'amount' => 1470, 'vendor' => 'IKEA',                   'account' => '1510'],
                    ['month' => '2024-03', 'category' => 'Software and Subscriptions', 'desc' => 'Figma Professional (licence annuelle)',         'amount' => 540,  'vendor' => 'Figma Inc.',             'account' => '6530'],
                    ['month' => '2024-05', 'category' => 'Office Supplies',            'desc' => 'Écrans Dell 27" (2×)',                         'amount' => 980,  'vendor' => 'Digitec Galaxus SA',     'account' => '1510'],
                    ['month' => '2024-07', 'category' => 'Travel Expenses',            'desc' => 'Abonnement demi-tarif Marc',                   'amount' => 185,  'vendor' => 'CFF',                    'account' => '6700'],
                    ['month' => '2024-09', 'category' => 'Professional Services',      'desc' => 'Authentification notariale modification contrat','amount' => 350, 'vendor' => 'Notaire Genève',         'account' => '6570'],
                    ['month' => '2024-11', 'category' => 'Advertising and Marketing',  'desc' => 'Imprimerie — cartes de visite & flyers',       'amount' => 420,  'vendor' => 'Imprimerie Genevoise',   'account' => '6600'],
                    ['month' => '2025-01', 'category' => 'IT Equipment',               'desc' => 'NAS Synology DS923+ + disques durs',           'amount' => 1350, 'vendor' => 'Digitec Galaxus SA',     'account' => '1520'],
                    ['month' => '2025-03', 'category' => 'Software and Subscriptions', 'desc' => 'JetBrains All Products Pack (licence annuelle)','amount' => 649,  'vendor' => 'JetBrains s.r.o.',       'account' => '6530'],
                    ['month' => '2025-04', 'category' => 'Travel Expenses',            'desc' => 'Conférence WordCamp Zürich — 2 billets',       'amount' => 390,  'vendor' => 'WordCamp Suisse',        'account' => '6700'],
                    ['month' => '2025-06', 'category' => 'IT Equipment',               'desc' => 'MacBook Air M3 — poste Nadia',                 'amount' => 1599, 'vendor' => 'Apple Store',            'account' => '1520'],
                    ['month' => '2025-08', 'category' => 'Office Supplies',            'desc' => 'Papier imprimante + toner (6 mois)',           'amount' => 180,  'vendor' => 'Lyreco',                 'account' => '6500'],
                    ['month' => '2025-10', 'category' => 'Professional Services',      'desc' => 'Conseil fiscal — bouclement 2024',             'amount' => 1200, 'vendor' => 'Fiduciaire Bonvin SA',   'account' => '6570'],
                    ['month' => '2025-12', 'category' => 'Advertising and Marketing',  'desc' => 'Cartes de Noël & cadeaux clients',             'amount' => 340,  'vendor' => 'Bucherer SA',            'account' => '6600'],
                    ['month' => '2026-01', 'category' => 'IT Equipment',               'desc' => 'MacBook Pro 16" M4 — poste Marc',              'amount' => 3299, 'vendor' => 'Apple Store',            'account' => '1520'],
                    ['month' => '2026-02', 'category' => 'Software and Subscriptions', 'desc' => 'Notion Team Plan (licence annuelle)',           'amount' => 288,  'vendor' => 'Notion Labs Inc.',       'account' => '6530'],
                    ['month' => '2026-03', 'category' => 'Travel Expenses',            'desc' => 'Visite client Berne — CFF + hôtel',            'amount' => 465,  'vendor' => 'CFF',                    'account' => '6700'],
                ],
                'invoiceNotes'    => 'Merci pour votre confiance.',
                'cancelledNotes'  => 'Annulée — périmètre du projet modifié',
                'partialRef'      => 'Paiement partiel',
                'draftProjects'   => [
                    ['cust' => 2, 'desc' => 'Portail patient Phase 3 — ébauche',     'hours' => 35, 'rate' => 190],
                    ['cust' => 5, 'desc' => 'Extension analytics du tableau de bord', 'hours' => 20, 'rate' => 205],
                ],
                'pendingExpenses' => [
                    ['category' => 'Travel Expenses', 'desc' => 'Billet CFF Genève–Berne (visite client)', 'amount' => 88, 'vendor' => 'CFF'],
                    ['category' => 'Office Supplies', 'desc' => 'Tableau blanc + marqueurs (brainstorming)', 'amount' => 210, 'vendor' => 'Lyreco'],
                ],
                'unmatchedBankTx' => [
                    ['desc' => 'Paiement TWINT — inconnu',                              'amount' => 150,  'type' => 'credit', 'ref' => 'TWINT-REF-99882'],
                    ['desc' => 'Prélèvement Mobilière Assurance — prime annuelle',       'amount' => 2340, 'type' => 'debit',  'ref' => 'LSV-MOB-2026-Q1'],
                ],
                'paymentLabel' => 'Paiement',
                'partialLabel' => 'Paiement partiel',
            ],

            'it' => [
                'org' => ['name' => 'AlpinCode Sagl', 'legal_name' => 'AlpinCode Sagl', 'address' => 'Via Nassa 32', 'city' => 'Lugano', 'postal_code' => '6900', 'canton' => 'TI', 'vat_number' => 'CHE-567.890.123 IVA'],
                'prefix' => 'AC',
                'bank' => ['iban' => 'CH12 0024 0024 1234 5678 9', 'name' => 'Conto corrente', 'bank_name' => 'Banca dello Stato del Cantone Ticino'],
                'users' => [
                    ['email' => 'giulia@alpincode.ch',  'name' => 'Giulia Bernasconi', 'role' => 'owner'],
                    ['email' => 'marco@alpincode.ch',   'name' => 'Marco Bentivoglio', 'role' => 'member'],
                    ['email' => 'elena@alpincode.ch',   'name' => 'Elena Fontana',     'role' => 'member'],
                ],
                'customers' => [
                    ['name' => 'Immobiliare Monte SA',   'email' => 'info@immobiliare-monte.ch',  'address' => 'Via Pessina 10',       'city' => 'Lugano',      'postal_code' => '6900', 'phone' => '+41 91 922 12 34', 'vat_number' => 'CHE-333.444.555 IVA', 'payment_terms' => 'Net 30'],
                    ['name' => 'Caseificio Alpino Sagl', 'email' => 'info@caseificio-alpino.ch',  'address' => 'Via del Borgo 5',      'city' => 'Bellinzona',  'postal_code' => '6500', 'phone' => '+41 91 825 55 66'],
                    ['name' => 'Clinica Lago Ceresio',   'email' => 'it@clinica-ceresio.ch',      'address' => 'Via al Lido 8',        'city' => 'Paradiso',    'postal_code' => '6902', 'phone' => '+41 91 985 88 00', 'vat_number' => 'CHE-666.777.888 IVA', 'payment_terms' => 'Net 45'],
                    ['name' => 'Ente Turistico Lugano',  'email' => 'digital@turismo-lugano.ch',  'address' => 'Piazza della Riforma', 'city' => 'Lugano',      'postal_code' => '6900', 'phone' => '+41 91 913 32 32'],
                    ['name' => 'Falegnameria Bentivoglio','email' => 'info@bentivoglio-legno.ch', 'address' => 'Via Industria 14',     'city' => 'Manno',       'postal_code' => '6928', 'phone' => '+41 91 611 33 44'],
                    ['name' => 'TicinoFinanza SA',       'email' => 'progetti@ticinofinanza.ch',  'address' => 'Corso Elvezia 16',     'city' => 'Lugano',      'postal_code' => '6900', 'phone' => '+41 91 910 90 00', 'vat_number' => 'CHE-999.000.111 IVA', 'payment_terms' => 'Net 30'],
                    ['name' => 'Ciclofficina del Lago',  'email' => 'ciao@ciclofficina.ch',       'address' => 'Via Cattori 3',        'city' => 'Lugano',      'postal_code' => '6900', 'phone' => '+41 91 971 67 89'],
                ],
                'suppliers' => [
                    ['name' => 'Gestioni Immobiliari Ticino',  'email' => 'admin@gestioni-ti.ch',     'default_expense_category' => 'Rent'],
                    ['name' => 'Swisscom SA',                  'email' => 'aziende@swisscom.ch',      'default_expense_category' => 'Telephone and Internet'],
                    ['name' => 'Digitec Galaxus SA',           'email' => 'business@digitec.ch',      'default_expense_category' => 'IT Equipment'],
                    ['name' => 'Lyreco Svizzera SA',           'email' => 'servizio@lyreco.ch',       'default_expense_category' => 'Office Supplies'],
                ],
                'monthlyExpenses' => [
                    ['category' => 'Rent',                       'desc' => 'Affitto ufficio Via Nassa 32',                 'amount' => 1900, 'vendor' => 'Gestioni Immobiliari Ticino', 'account' => '6000'],
                    ['category' => 'Telephone and Internet',     'desc' => 'Swisscom Internet e telefonia',                'amount' => 129,  'vendor' => 'Swisscom SA',                'account' => '6510'],
                    ['category' => 'Software and Subscriptions', 'desc' => 'Microsoft 365 Business',                       'amount' => 66,   'vendor' => 'Microsoft Ireland',          'account' => '6530'],
                    ['category' => 'Software and Subscriptions', 'desc' => 'GitHub Team',                                  'amount' => 38,   'vendor' => 'GitHub Inc.',                'account' => '6530'],
                    ['category' => 'Insurance',                  'desc' => 'Assicurazione responsabilità professionale',   'amount' => 190,  'vendor' => 'Mobiliare',                  'account' => '6300'],
                ],
                'quarterlyExpenses' => [
                    ['category' => 'Accounting and Legal Fees', 'desc' => 'Consulenza contabile trimestrale',   'amount' => 680, 'vendor' => 'Fiduciaria Rezzonico',  'account' => '6570'],
                    ['category' => 'Advertising and Marketing', 'desc' => 'Campagna Google Ads',                'amount' => 460, 'vendor' => 'Google Ireland Ltd.',   'account' => '6600'],
                ],
                'projects' => [
                    ['cust' => 0, 'desc' => 'Rifacimento sito web',                   'rate' => 165, 'hMin' => 20, 'hMax' => 60],
                    ['cust' => 0, 'desc' => 'Ottimizzazione SEO & analytics',          'rate' => 150, 'hMin' => 8,  'hMax' => 20],
                    ['cust' => 0, 'desc' => 'Portale immobiliare',                     'rate' => 180, 'hMin' => 40, 'hMax' => 80],
                    ['cust' => 1, 'desc' => 'Integrazione negozio online',             'rate' => 160, 'hMin' => 15, 'hMax' => 40],
                    ['cust' => 1, 'desc' => 'Sistema newsletter',                      'rate' => 140, 'hMin' => 5,  'hMax' => 15],
                    ['cust' => 2, 'desc' => 'Portale pazienti MVP',                    'rate' => 190, 'hMin' => 30, 'hMax' => 80],
                    ['cust' => 2, 'desc' => 'Modulo prenotazione appuntamenti',        'rate' => 175, 'hMin' => 20, 'hMax' => 50],
                    ['cust' => 3, 'desc' => 'App calendario eventi',                   'rate' => 170, 'hMin' => 25, 'hMax' => 60],
                    ['cust' => 3, 'desc' => 'Sito web multilingue',                    'rate' => 155, 'hMin' => 30, 'hMax' => 70],
                    ['cust' => 4, 'desc' => 'Digitalizzazione gestione ordini',        'rate' => 150, 'hMin' => 15, 'hMax' => 35],
                    ['cust' => 5, 'desc' => 'Dashboard conformità',                    'rate' => 200, 'hMin' => 40, 'hMax' => 100],
                    ['cust' => 5, 'desc' => 'Automazione workflow KYC',                'rate' => 195, 'hMin' => 30, 'hMax' => 80],
                    ['cust' => 6, 'desc' => 'Piattaforma prenotazione online',         'rate' => 155, 'hMin' => 20, 'hMax' => 45],
                ],
                'extraLines' => ['Hosting e manutenzione', 'Forfait supporto', 'Registrazione dominio', 'Certificato SSL', 'Hosting e-mail', 'Servizio backup'],
                'salaries' => [
                    ['desc' => 'Stipendio Giulia Bernasconi', 'gross' => 7400, 'social' => 962],
                    ['desc' => 'Stipendio Marco Bentivoglio', 'gross' => 6900, 'social' => 897],
                    ['desc' => 'Stipendio Elena Fontana',     'gross' => 6700, 'social' => 871],
                ],
                'salaryLabel'  => 'Contributi sociali',
                'occasionalExpenses' => [
                    ['month' => '2024-01', 'category' => 'IT Equipment',              'desc' => 'MacBook Pro 14" M3 — postazione Giulia',    'amount' => 2899, 'vendor' => 'Apple Store',            'account' => '1520'],
                    ['month' => '2024-02', 'category' => 'Office Supplies',            'desc' => 'Sedie ufficio (3×)',                         'amount' => 1470, 'vendor' => 'IKEA',                   'account' => '1510'],
                    ['month' => '2024-03', 'category' => 'Software and Subscriptions', 'desc' => 'Figma Professional (licenza annuale)',       'amount' => 540,  'vendor' => 'Figma Inc.',             'account' => '6530'],
                    ['month' => '2024-05', 'category' => 'Office Supplies',            'desc' => 'Monitor Dell 27" (2×)',                      'amount' => 980,  'vendor' => 'Digitec Galaxus SA',     'account' => '1510'],
                    ['month' => '2024-07', 'category' => 'Travel Expenses',            'desc' => 'Abbonamento metà-prezzo Marco',              'amount' => 185,  'vendor' => 'FFS',                    'account' => '6700'],
                    ['month' => '2024-09', 'category' => 'Professional Services',      'desc' => 'Autenticazione notarile modifica contratto', 'amount' => 350,  'vendor' => 'Notaio Lugano',          'account' => '6570'],
                    ['month' => '2024-11', 'category' => 'Advertising and Marketing',  'desc' => 'Tipografia — biglietti da visita & volantini','amount' => 420, 'vendor' => 'Tipografia Luganese',    'account' => '6600'],
                    ['month' => '2025-01', 'category' => 'IT Equipment',               'desc' => 'NAS Synology DS923+ + dischi rigidi',        'amount' => 1350, 'vendor' => 'Digitec Galaxus SA',     'account' => '1520'],
                    ['month' => '2025-03', 'category' => 'Software and Subscriptions', 'desc' => 'JetBrains All Products Pack (licenza annuale)','amount' => 649, 'vendor' => 'JetBrains s.r.o.',       'account' => '6530'],
                    ['month' => '2025-04', 'category' => 'Travel Expenses',            'desc' => 'Conferenza WordCamp Zurigo — 2 biglietti',   'amount' => 390,  'vendor' => 'WordCamp Svizzera',      'account' => '6700'],
                    ['month' => '2025-06', 'category' => 'IT Equipment',               'desc' => 'MacBook Air M3 — postazione Elena',          'amount' => 1599, 'vendor' => 'Apple Store',            'account' => '1520'],
                    ['month' => '2025-08', 'category' => 'Office Supplies',            'desc' => 'Carta da stampa + toner (fornitura 6 mesi)', 'amount' => 180,  'vendor' => 'Lyreco',                 'account' => '6500'],
                    ['month' => '2025-10', 'category' => 'Professional Services',      'desc' => 'Consulenza fiscale — chiusura 2024',         'amount' => 1200, 'vendor' => 'Fiduciaria Rezzonico',   'account' => '6570'],
                    ['month' => '2025-12', 'category' => 'Advertising and Marketing',  'desc' => 'Biglietti di Natale & regali clienti',       'amount' => 340,  'vendor' => 'Bucherer SA',            'account' => '6600'],
                    ['month' => '2026-01', 'category' => 'IT Equipment',               'desc' => 'MacBook Pro 16" M4 — postazione Marco',      'amount' => 3299, 'vendor' => 'Apple Store',            'account' => '1520'],
                    ['month' => '2026-02', 'category' => 'Software and Subscriptions', 'desc' => 'Notion Team Plan (licenza annuale)',          'amount' => 288,  'vendor' => 'Notion Labs Inc.',       'account' => '6530'],
                    ['month' => '2026-03', 'category' => 'Travel Expenses',            'desc' => 'Visita cliente Berna — FFS + hotel',          'amount' => 465,  'vendor' => 'FFS',                    'account' => '6700'],
                ],
                'invoiceNotes'    => 'Grazie per la collaborazione.',
                'cancelledNotes'  => 'Annullata — ambito del progetto modificato',
                'partialRef'      => 'Pagamento parziale',
                'draftProjects'   => [
                    ['cust' => 2, 'desc' => 'Portale pazienti Fase 3 — bozza',      'hours' => 35, 'rate' => 190],
                    ['cust' => 5, 'desc' => 'Estensione analytics della dashboard',  'hours' => 20, 'rate' => 200],
                ],
                'pendingExpenses' => [
                    ['category' => 'Travel Expenses', 'desc' => 'Biglietto FFS Lugano–Berna (visita cliente)', 'amount' => 88, 'vendor' => 'FFS'],
                    ['category' => 'Office Supplies', 'desc' => 'Lavagna bianca + pennarelli (brainstorming)', 'amount' => 210, 'vendor' => 'Lyreco'],
                ],
                'unmatchedBankTx' => [
                    ['desc' => 'Pagamento TWINT — sconosciuto',                      'amount' => 150,  'type' => 'credit', 'ref' => 'TWINT-REF-99883'],
                    ['desc' => 'Addebito Mobiliare Assicurazione — premio annuale',  'amount' => 2280, 'type' => 'debit',  'ref' => 'LSV-MOB-2026-Q1'],
                ],
                'paymentLabel' => 'Pagamento',
                'partialLabel' => 'Pagamento parziale',
            ],

            'en' => [
                'org' => ['name' => 'BrightPath Solutions Ltd', 'legal_name' => 'BrightPath Solutions Ltd', 'address' => 'Limmatquai 72', 'city' => 'Zürich', 'postal_code' => '8001', 'canton' => 'ZH', 'vat_number' => 'CHE-678.901.234 MWST'],
                'prefix' => 'BP',
                'bank' => ['iban' => 'CH78 0070 0110 0012 3456 7', 'name' => 'Business Account', 'bank_name' => 'Zürcher Kantonalbank'],
                'users' => [
                    ['email' => 'anna@brightpath.ch',   'name' => 'Anna Weber',     'role' => 'owner'],
                    ['email' => 'james@brightpath.ch',  'name' => 'James Hartmann', 'role' => 'member'],
                    ['email' => 'priya@brightpath.ch',  'name' => 'Priya Sharma',   'role' => 'member'],
                ],
                'customers' => [
                    ['name' => 'Alpine Properties AG',    'email' => 'contact@alpine-properties.ch',  'address' => 'Bahnhofstrasse 50',       'city' => 'Zürich',      'postal_code' => '8001', 'phone' => '+41 44 210 12 34', 'vat_number' => 'CHE-100.200.300 MWST', 'payment_terms' => 'Net 30'],
                    ['name' => 'Swiss Dairy Collective',  'email' => 'orders@swissdairy.ch',          'address' => 'Bergstrasse 12',           'city' => 'Winterthur',  'postal_code' => '8400', 'phone' => '+41 52 215 55 66'],
                    ['name' => 'Lake View Medical Centre','email' => 'it@lakeview-medical.ch',        'address' => 'Seestrasse 80',            'city' => 'Zürich',      'postal_code' => '8002', 'phone' => '+41 44 386 88 00', 'vat_number' => 'CHE-400.500.600 MWST', 'payment_terms' => 'Net 45'],
                    ['name' => 'Zürich Tourism Board',    'email' => 'digital@zurich-tourism.ch',     'address' => 'Stampfenbachstrasse 52',   'city' => 'Zürich',      'postal_code' => '8006', 'phone' => '+41 44 215 40 00'],
                    ['name' => 'Steiner Woodworks GmbH',  'email' => 'info@steiner-woodworks.ch',     'address' => 'Gewerbestrasse 11',        'city' => 'Uster',       'postal_code' => '8610', 'phone' => '+41 44 940 33 44'],
                    ['name' => 'ZuriFinance AG',          'email' => 'projects@zurifinance.ch',       'address' => 'Paradeplatz 6',            'city' => 'Zürich',      'postal_code' => '8001', 'phone' => '+41 44 225 90 00', 'vat_number' => 'CHE-700.800.900 MWST', 'payment_terms' => 'Net 30'],
                    ['name' => 'City Cycles Zürich',      'email' => 'hello@citycycles-zh.ch',        'address' => 'Langstrasse 88',           'city' => 'Zürich',      'postal_code' => '8004', 'phone' => '+41 44 291 67 89'],
                ],
                'suppliers' => [
                    ['name' => 'Swiss Property Management AG', 'email' => 'admin@spm-zh.ch',            'default_expense_category' => 'Rent'],
                    ['name' => 'Swisscom AG',                  'email' => 'enterprise@swisscom.ch',     'default_expense_category' => 'Telephone and Internet'],
                    ['name' => 'Digitec Galaxus AG',           'email' => 'b2b@digitec.ch',             'default_expense_category' => 'IT Equipment'],
                    ['name' => 'Office World AG',              'email' => 'orders@officeworld.ch',      'default_expense_category' => 'Office Supplies'],
                ],
                'monthlyExpenses' => [
                    ['category' => 'Rent',                       'desc' => 'Office rent — Limmatquai 72',                  'amount' => 2200, 'vendor' => 'Swiss Property Management AG', 'account' => '6000'],
                    ['category' => 'Telephone and Internet',     'desc' => 'Swisscom Internet & phone',                    'amount' => 139,  'vendor' => 'Swisscom AG',                 'account' => '6510'],
                    ['category' => 'Software and Subscriptions', 'desc' => 'Microsoft 365 Business',                       'amount' => 66,   'vendor' => 'Microsoft Ireland',           'account' => '6530'],
                    ['category' => 'Software and Subscriptions', 'desc' => 'GitHub Team',                                  'amount' => 38,   'vendor' => 'GitHub Inc.',                 'account' => '6530'],
                    ['category' => 'Insurance',                  'desc' => 'Professional liability insurance',             'amount' => 195,  'vendor' => 'Mobiliar',                    'account' => '6300'],
                ],
                'quarterlyExpenses' => [
                    ['category' => 'Accounting and Legal Fees', 'desc' => 'Quarterly accounting advisory',     'amount' => 720, 'vendor' => 'BWL Treuhand AG',   'account' => '6570'],
                    ['category' => 'Advertising and Marketing', 'desc' => 'Google Ads campaign',               'amount' => 500, 'vendor' => 'Google Ireland Ltd.','account' => '6600'],
                ],
                'projects' => [
                    ['cust' => 0, 'desc' => 'Website redesign',                   'rate' => 175, 'hMin' => 20, 'hMax' => 60],
                    ['cust' => 0, 'desc' => 'SEO optimization & analytics',       'rate' => 155, 'hMin' => 8,  'hMax' => 20],
                    ['cust' => 0, 'desc' => 'Property portal development',        'rate' => 190, 'hMin' => 40, 'hMax' => 80],
                    ['cust' => 1, 'desc' => 'E-commerce integration',             'rate' => 170, 'hMin' => 15, 'hMax' => 40],
                    ['cust' => 1, 'desc' => 'Newsletter system',                  'rate' => 150, 'hMin' => 5,  'hMax' => 15],
                    ['cust' => 2, 'desc' => 'Patient portal MVP',                 'rate' => 195, 'hMin' => 30, 'hMax' => 80],
                    ['cust' => 2, 'desc' => 'Appointment booking module',         'rate' => 185, 'hMin' => 20, 'hMax' => 50],
                    ['cust' => 3, 'desc' => 'Event calendar application',         'rate' => 180, 'hMin' => 25, 'hMax' => 60],
                    ['cust' => 3, 'desc' => 'Multilingual website',               'rate' => 165, 'hMin' => 30, 'hMax' => 70],
                    ['cust' => 4, 'desc' => 'Order management digitalization',    'rate' => 160, 'hMin' => 15, 'hMax' => 35],
                    ['cust' => 5, 'desc' => 'Compliance dashboard',               'rate' => 210, 'hMin' => 40, 'hMax' => 100],
                    ['cust' => 5, 'desc' => 'KYC workflow automation',            'rate' => 205, 'hMin' => 30, 'hMax' => 80],
                    ['cust' => 6, 'desc' => 'Online booking platform',            'rate' => 165, 'hMin' => 20, 'hMax' => 45],
                ],
                'extraLines' => ['Hosting & maintenance', 'Support package', 'Domain registration', 'SSL certificate', 'Email hosting', 'Backup service'],
                'salaries' => [
                    ['desc' => 'Salary Anna Weber',     'gross' => 8000, 'social' => 1040],
                    ['desc' => 'Salary James Hartmann', 'gross' => 7500, 'social' => 975],
                    ['desc' => 'Salary Priya Sharma',   'gross' => 7200, 'social' => 936],
                ],
                'salaryLabel'  => 'Social security contributions',
                'occasionalExpenses' => [
                    ['month' => '2024-01', 'category' => 'IT Equipment',              'desc' => 'MacBook Pro 14" M3 — Anna\'s workstation',   'amount' => 2899, 'vendor' => 'Apple Store',            'account' => '1520'],
                    ['month' => '2024-02', 'category' => 'Office Supplies',            'desc' => 'Office chairs (3×)',                          'amount' => 1470, 'vendor' => 'IKEA',                   'account' => '1510'],
                    ['month' => '2024-03', 'category' => 'Software and Subscriptions', 'desc' => 'Figma Professional (annual license)',         'amount' => 540,  'vendor' => 'Figma Inc.',             'account' => '6530'],
                    ['month' => '2024-05', 'category' => 'Office Supplies',            'desc' => 'Dell 27" monitors (2×)',                      'amount' => 980,  'vendor' => 'Digitec Galaxus AG',     'account' => '1510'],
                    ['month' => '2024-07', 'category' => 'Travel Expenses',            'desc' => 'Half-fare travel card — James',               'amount' => 185,  'vendor' => 'SBB CFF FFS',            'account' => '6700'],
                    ['month' => '2024-09', 'category' => 'Professional Services',      'desc' => 'Notarial certification — contract amendment', 'amount' => 350,  'vendor' => 'Notary Zürich',          'account' => '6570'],
                    ['month' => '2024-11', 'category' => 'Advertising and Marketing',  'desc' => 'Print shop — business cards & flyers',        'amount' => 420,  'vendor' => 'Print Solutions AG',     'account' => '6600'],
                    ['month' => '2025-01', 'category' => 'IT Equipment',               'desc' => 'NAS Synology DS923+ with hard drives',        'amount' => 1350, 'vendor' => 'Digitec Galaxus AG',     'account' => '1520'],
                    ['month' => '2025-03', 'category' => 'Software and Subscriptions', 'desc' => 'JetBrains All Products Pack (annual license)','amount' => 649,  'vendor' => 'JetBrains s.r.o.',       'account' => '6530'],
                    ['month' => '2025-04', 'category' => 'Travel Expenses',            'desc' => 'WordCamp Zürich conference — 2 tickets',      'amount' => 390,  'vendor' => 'WordCamp Switzerland',   'account' => '6700'],
                    ['month' => '2025-06', 'category' => 'IT Equipment',               'desc' => 'MacBook Air M3 — Priya\'s workstation',       'amount' => 1599, 'vendor' => 'Apple Store',            'account' => '1520'],
                    ['month' => '2025-08', 'category' => 'Office Supplies',            'desc' => 'Printer paper + toner (6-month supply)',      'amount' => 180,  'vendor' => 'Office World AG',        'account' => '6500'],
                    ['month' => '2025-10', 'category' => 'Professional Services',      'desc' => 'Tax advisory — 2024 annual closing',          'amount' => 1200, 'vendor' => 'BWL Treuhand AG',        'account' => '6570'],
                    ['month' => '2025-12', 'category' => 'Advertising and Marketing',  'desc' => 'Christmas cards & client gifts',              'amount' => 340,  'vendor' => 'Bucherer AG',            'account' => '6600'],
                    ['month' => '2026-01', 'category' => 'IT Equipment',               'desc' => 'MacBook Pro 16" M4 — James\'s workstation',   'amount' => 3299, 'vendor' => 'Apple Store',            'account' => '1520'],
                    ['month' => '2026-02', 'category' => 'Software and Subscriptions', 'desc' => 'Notion Team Plan (annual license)',            'amount' => 288,  'vendor' => 'Notion Labs Inc.',       'account' => '6530'],
                    ['month' => '2026-03', 'category' => 'Travel Expenses',            'desc' => 'Client visit Bern — train + hotel',           'amount' => 465,  'vendor' => 'SBB CFF FFS',            'account' => '6700'],
                ],
                'invoiceNotes'    => 'Thank you for your business.',
                'cancelledNotes'  => 'Cancelled — project scope changed',
                'partialRef'      => 'Partial payment',
                'draftProjects'   => [
                    ['cust' => 2, 'desc' => 'Patient portal Phase 3 — draft',        'hours' => 35, 'rate' => 195],
                    ['cust' => 5, 'desc' => 'Dashboard analytics extension',         'hours' => 20, 'rate' => 210],
                ],
                'pendingExpenses' => [
                    ['category' => 'Travel Expenses', 'desc' => 'Train ticket Zürich–Bern (client meeting)', 'amount' => 88, 'vendor' => 'SBB CFF FFS'],
                    ['category' => 'Office Supplies', 'desc' => 'Whiteboard + markers (brainstorming kit)', 'amount' => 210, 'vendor' => 'Office World AG'],
                ],
                'unmatchedBankTx' => [
                    ['desc' => 'TWINT payment — unknown sender',                       'amount' => 150,  'type' => 'credit', 'ref' => 'TWINT-REF-99884'],
                    ['desc' => 'Direct debit Mobiliar Insurance — annual premium',     'amount' => 2340, 'type' => 'debit',  'ref' => 'LSV-MOB-2026-Q1'],
                ],
                'paymentLabel' => 'Payment',
                'partialLabel' => 'Partial payment',
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  run()
    // ──────────────────────────────────────────────────────────────

    public function run(): void
    {
        // Disable Scout indexing during seeding to avoid needing the jobs table
        $searchableModels = [Customer::class, Supplier::class, Invoice::class, Expense::class];
        foreach ($searchableModels as $model) {
            ModelObserver::disableSyncingFor($model);
        }

        foreach ($this->orgDefinitions() as $locale => $pack) {
            $this->seedLocale($locale, $pack);
        }

        foreach ($searchableModels as $model) {
            ModelObserver::enableSyncingFor($model);
        }
    }

    private function seedLocale(string $locale, array $pack): void
    {
        // ── Users ────────────────────────────────────────────────
        $users = [];
        foreach ($pack['users'] as $u) {
            $users[] = User::firstOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => Hash::make('password'), 'locale' => $locale]
            );
        }

        // ── Organization ─────────────────────────────────────────
        $org = Organization::where('name', $pack['org']['name'])->first()
            ?? Organization::create(array_merge($pack['org'], [
                'country'  => 'CH',
                'currency' => 'CHF',
                'locale'   => $locale,
                'fiscal_year_start'         => 1,
                'default_payment_terms_days' => 30,
            ]));

        $syncData = [];
        foreach ($pack['users'] as $i => $u) {
            $syncData[$users[$i]->id] = ['role' => $u['role']];
        }
        $org->users()->syncWithoutDetaching($syncData);

        app()[PermissionRegistrar::class]->setPermissionsTeamId($org->id);
        foreach ($pack['users'] as $i => $u) {
            $spatieRole = $u['role'] === 'owner' ? Role::Owner->value : Role::Member->value;
            $users[$i]->syncRoles([$spatieRole]);
        }

        // Chart of accounts & VAT
        if ($org->accounts()->count() === 0) {
            (new SwissChartOfAccountsSeeder())->run($org);
        }
        if (VatRate::where('organization_id', $org->id)->count() === 0) {
            (new SwissVatRatesSeeder())->run($org);
        }

        // Abort if already seeded
        if (Invoice::where('organization_id', $org->id)->exists()) {
            $this->command?->info("  {$pack['org']['name']} — already seeded, skipping.");
            return;
        }

        $vatNormal = VatRate::where('organization_id', $org->id)->where('code', 'NORMAL')->first();

        // ── Bank Account ─────────────────────────────────────────
        $bankLedger = Account::where('organization_id', $org->id)->where('code', '1020')->firstOrFail();
        $bankAccount = BankAccount::firstOrCreate(
            ['organization_id' => $org->id, 'iban' => $pack['bank']['iban']],
            [
                'account_id' => $bankLedger->id,
                'name'       => $pack['bank']['name'],
                'bank_name'  => $pack['bank']['bank_name'],
                'currency'   => 'CHF',
                'balance'    => 30000.00,
            ]
        );

        // ── Customers ────────────────────────────────────────────
        $customers = [];
        foreach ($pack['customers'] as $c) {
            $customers[] = Customer::firstOrCreate(
                ['organization_id' => $org->id, 'email' => $c['email']],
                array_merge($c, ['organization_id' => $org->id, 'country' => 'CH'])
            );
        }

        // ── Suppliers ────────────────────────────────────────────
        foreach ($pack['suppliers'] as $s) {
            Supplier::firstOrCreate(
                ['organization_id' => $org->id, 'email' => $s['email']],
                array_merge($s, ['organization_id' => $org->id, 'country' => 'CH'])
            );
        }

        // ──────────────────────────────────────────────────────────
        //  3 YEARS OF DATA (2024 → Mar 2026)
        // ──────────────────────────────────────────────────────────

        $invoiceNum = 0;
        $bankTxNum  = 0;

        mt_srand(crc32($locale)); // reproducible but different per locale

        for ($year = 2024; $year <= 2026; $year++) {
            $maxMonth = ($year === 2026) ? 3 : 12;

            for ($month = 1; $month <= $maxMonth; $month++) {
                $monthStart = Carbon::create($year, $month, 1);

                // ── Monthly recurring expenses ───────────────────
                foreach ($pack['monthlyExpenses'] as $me) {
                    $date = $monthStart->copy()->addDays(mt_rand(0, 5));
                    $this->createPostedExpense($org, $me, $date, $vatNormal, $bankAccount, $bankTxNum);
                }

                // ── Quarterly expenses (Jan, Apr, Jul, Oct) ──────
                if (in_array($month, [1, 4, 7, 10])) {
                    foreach ($pack['quarterlyExpenses'] as $qe) {
                        $date = $monthStart->copy()->addDays(mt_rand(5, 15));
                        $this->createPostedExpense($org, $qe, $date, $vatNormal, $bankAccount, $bankTxNum);
                    }
                }

                // ── Salary payments (25th) ───────────────────────
                $salaryDate = $monthStart->copy()->day(25);
                if ($salaryDate->isFuture()) {
                    $salaryDate = now()->subDay();
                }
                foreach ($pack['salaries'] as $sal) {
                    $bankTxNum++;
                    $tx = BankTransaction::create([
                        'bank_account_id' => $bankAccount->id,
                        'date'            => $salaryDate,
                        'description'     => $sal['desc'] . ' — ' . $monthStart->translatedFormat('F Y'),
                        'amount'          => $sal['gross'],
                        'type'            => BankTransactionType::Debit,
                        'reference'       => sprintf('SAL-%04d-%03d', $year, $bankTxNum),
                    ]);
                    $this->bankingService->postBankTransaction($tx, '5000');

                    $bankTxNum++;
                    $nameParts = explode(' ', $sal['desc']);
                    $shortName = $nameParts[count($nameParts) - 2] . ' ' . $nameParts[count($nameParts) - 1];
                    $txSocial = BankTransaction::create([
                        'bank_account_id' => $bankAccount->id,
                        'date'            => $salaryDate,
                        'description'     => $pack['salaryLabel'] . ' — ' . $shortName,
                        'amount'          => $sal['social'],
                        'type'            => BankTransactionType::Debit,
                        'reference'       => sprintf('SOC-%04d-%03d', $year, $bankTxNum),
                    ]);
                    $this->bankingService->postBankTransaction($txSocial, '5700');
                }

                // ── Client invoices — 2-4 per month ──────────────
                $invoiceCount = mt_rand(2, 4);
                for ($i = 0; $i < $invoiceCount; $i++) {
                    $invoiceNum++;
                    $project   = $pack['projects'][mt_rand(0, count($pack['projects']) - 1)];
                    $customer  = $customers[$project['cust']];
                    $hours     = mt_rand($project['hMin'], $project['hMax']);
                    $subtotal  = round($hours * $project['rate'], 2);
                    $vatAmount = round($subtotal * 0.081, 2);
                    $total     = round($subtotal + $vatAmount, 2);
                    $issueDate = $monthStart->copy()->addDays(mt_rand(0, 20));
                    $dueDate   = $issueDate->copy()->addDays(30);
                    $invNumber = sprintf('%s-%04d-%03d', $pack['prefix'], $year, $invoiceNum);

                    $invoice = Invoice::create([
                        'organization_id' => $org->id,
                        'customer_id'     => $customer->id,
                        'number'          => $invNumber,
                        'status'          => InvoiceStatus::Draft->value,
                        'issue_date'      => $issueDate,
                        'due_date'        => $dueDate,
                        'subtotal'        => $subtotal,
                        'vat_amount'      => $vatAmount,
                        'total'           => $total,
                        'currency'        => 'CHF',
                        'notes'           => $pack['invoiceNotes'],
                        'payment_terms'   => 'Net 30',
                    ]);

                    InvoiceLine::create([
                        'invoice_id'  => $invoice->id,
                        'description' => $project['desc'] . ' — ' . $monthStart->translatedFormat('F Y'),
                        'quantity'    => $hours,
                        'unit_price'  => $project['rate'],
                        'amount'      => $subtotal,
                        'vat_rate_id' => $vatNormal?->id,
                        'vat_amount'  => $vatAmount,
                        'sort_order'  => 1,
                    ]);

                    // Extra line item ~33% of the time
                    if (mt_rand(1, 3) === 1) {
                        $extraAmt = mt_rand(1, 5) * 50;
                        $extraVat = round($extraAmt * 0.081, 2);
                        InvoiceLine::create([
                            'invoice_id'  => $invoice->id,
                            'description' => $pack['extraLines'][mt_rand(0, count($pack['extraLines']) - 1)],
                            'quantity'    => 1,
                            'unit_price'  => $extraAmt,
                            'amount'      => $extraAmt,
                            'vat_rate_id' => $vatNormal?->id,
                            'vat_amount'  => $extraVat,
                            'sort_order'  => 2,
                        ]);
                        $invoice->update([
                            'subtotal'   => $subtotal + $extraAmt,
                            'vat_amount' => $vatAmount + $extraVat,
                            'total'      => $total + $extraAmt + $extraVat,
                        ]);
                        $total = $total + $extraAmt + $extraVat;
                    }

                    $this->finalizeInvoice->execute($invoice);

                    // ── Payment logic based on age ───────────────
                    $daysSince = $issueDate->diffInDays(now());

                    if ($daysSince > 45) {
                        if (mt_rand(1, 10) <= 9) {
                            $invoice->refresh();
                            $payDate = $issueDate->copy()->addDays(mt_rand(15, 35));
                            $this->invoiceService->recordPayment($invoice, new RecordPaymentData(
                                amount: (string)$total,
                                paymentDate: $payDate->toDateString(),
                                paymentMethod: mt_rand(1, 4) === 1 ? PaymentMethod::Card : PaymentMethod::Bank,
                                reference: null,
                            ));
                            $bankTxNum++;
                            BankTransaction::create([
                                'bank_account_id' => $bankAccount->id,
                                'date'            => $payDate,
                                'description'     => $pack['paymentLabel'] . ' ' . $invNumber . ' — ' . $customer->name,
                                'amount'          => $total,
                                'type'            => BankTransactionType::Credit,
                                'reference'       => sprintf('DEP-%04d-%03d', $year, $bankTxNum),
                            ]);
                        }
                    } elseif ($daysSince > 30) {
                        $roll = mt_rand(1, 10);
                        if ($roll <= 5) {
                            $invoice->refresh();
                            $payDate = $issueDate->copy()->addDays(mt_rand(25, 40));
                            $this->invoiceService->recordPayment($invoice, new RecordPaymentData(
                                amount: (string)$total,
                                paymentDate: $payDate->toDateString(),
                                paymentMethod: PaymentMethod::Bank,
                                reference: null,
                            ));
                            $bankTxNum++;
                            BankTransaction::create([
                                'bank_account_id' => $bankAccount->id,
                                'date'            => $payDate,
                                'description'     => $pack['paymentLabel'] . ' ' . $invNumber . ' — ' . $customer->name,
                                'amount'          => $total,
                                'type'            => BankTransactionType::Credit,
                                'reference'       => sprintf('DEP-%04d-%03d', $year, $bankTxNum),
                            ]);
                        } elseif ($roll <= 7) {
                            $partAmt = round($total * (mt_rand(30, 60) / 100), 2);
                            $invoice->refresh();
                            $payDate = $issueDate->copy()->addDays(mt_rand(20, 35));
                            $this->invoiceService->recordPayment($invoice, new RecordPaymentData(
                                amount: (string)$partAmt,
                                paymentDate: $payDate->toDateString(),
                                paymentMethod: PaymentMethod::Bank,
                                reference: $pack['partialRef'],
                            ));
                            $bankTxNum++;
                            BankTransaction::create([
                                'bank_account_id' => $bankAccount->id,
                                'date'            => $payDate,
                                'description'     => $pack['partialLabel'] . ' ' . $invNumber . ' — ' . $customer->name,
                                'amount'          => $partAmt,
                                'type'            => BankTransactionType::Credit,
                                'reference'       => sprintf('DEP-%04d-%03d', $year, $bankTxNum),
                            ]);
                        }
                    }
                }

                // ── Cancelled invoice every ~4 months ────────────
                if ($month % 4 === 0) {
                    $invoiceNum++;
                    $cp = $pack['projects'][mt_rand(0, count($pack['projects']) - 1)];
                    $cc = $customers[$cp['cust']];
                    $ch = mt_rand(5, 15);
                    $cs = round($ch * $cp['rate'], 2);
                    $cv = round($cs * 0.081, 2);

                    $cancelInv = Invoice::create([
                        'organization_id' => $org->id,
                        'customer_id'     => $cc->id,
                        'number'          => sprintf('%s-%04d-%03d', $pack['prefix'], $year, $invoiceNum),
                        'status'          => InvoiceStatus::Cancelled,
                        'issue_date'      => $monthStart->copy()->addDays(mt_rand(0, 10)),
                        'due_date'        => $monthStart->copy()->addDays(40),
                        'subtotal'        => $cs,
                        'vat_amount'      => $cv,
                        'total'           => $cs + $cv,
                        'currency'        => 'CHF',
                        'notes'           => $pack['cancelledNotes'],
                    ]);

                    InvoiceLine::create([
                        'invoice_id'  => $cancelInv->id,
                        'description' => $cp['desc'] . ' (' . ($locale === 'de' ? 'annuliert' : ($locale === 'fr' ? 'annulée' : ($locale === 'it' ? 'annullata' : 'cancelled'))) . ')',
                        'quantity'    => $ch,
                        'unit_price'  => $cp['rate'],
                        'amount'      => $cs,
                        'vat_rate_id' => $vatNormal?->id,
                        'vat_amount'  => $cv,
                        'sort_order'  => 1,
                    ]);
                }
            }

            $invoiceNum = 0; // reset per year
        }

        // ── Occasional one-off expenses ──────────────────────────
        foreach ($pack['occasionalExpenses'] as $oe) {
            [$y, $m] = explode('-', $oe['month']);
            $date = Carbon::create((int)$y, (int)$m, mt_rand(1, 20));
            $this->createPostedExpense($org, $oe, $date, $vatNormal, $bankAccount, $bankTxNum);
        }

        // ── Draft invoices ───────────────────────────────────────
        foreach ($pack['draftProjects'] as $idx => $dp) {
            $sub = $dp['hours'] * $dp['rate'];
            $vat = round($sub * 0.081, 2);
            Invoice::create([
                'organization_id' => $org->id,
                'customer_id'     => $customers[$dp['cust']]->id,
                'number'          => sprintf('%s-2026-D%02d', $pack['prefix'], $idx + 1),
                'status'          => InvoiceStatus::Draft->value,
                'issue_date'      => now(),
                'due_date'        => now()->addDays(30),
                'subtotal'        => $sub,
                'vat_amount'      => $vat,
                'total'           => $sub + $vat,
                'currency'        => 'CHF',
                'payment_terms'   => 'Net 30',
            ]);
        }

        // ── Pending / approved expenses ──────────────────────────
        Expense::create([
            'organization_id' => $org->id,
            'category'        => $pack['pendingExpenses'][0]['category'],
            'description'     => $pack['pendingExpenses'][0]['desc'],
            'amount'          => $pack['pendingExpenses'][0]['amount'],
            'vat_amount'      => round($pack['pendingExpenses'][0]['amount'] * 0.081, 2),
            'date'            => now()->subDays(2),
            'vendor'          => $pack['pendingExpenses'][0]['vendor'],
            'status'          => ExpenseStatus::Pending->value,
            'currency'        => 'CHF',
        ]);

        $approvedExp = Expense::create([
            'organization_id' => $org->id,
            'vat_rate_id'     => $vatNormal?->id,
            'category'        => $pack['pendingExpenses'][1]['category'],
            'description'     => $pack['pendingExpenses'][1]['desc'],
            'amount'          => $pack['pendingExpenses'][1]['amount'],
            'vat_amount'      => round($pack['pendingExpenses'][1]['amount'] * 0.081, 2),
            'date'            => now()->subDays(5),
            'vendor'          => $pack['pendingExpenses'][1]['vendor'],
            'status'          => ExpenseStatus::Pending->value,
            'currency'        => 'CHF',
        ]);
        $this->approveExpense->execute($approvedExp);

        // ── Unmatched bank transactions ──────────────────────────
        foreach ($pack['unmatchedBankTx'] as $utx) {
            BankTransaction::create([
                'bank_account_id' => $bankAccount->id,
                'date'            => now()->subDays(mt_rand(1, 5)),
                'description'     => $utx['desc'],
                'amount'          => $utx['amount'],
                'type'            => $utx['type'] === 'credit' ? BankTransactionType::Credit : BankTransactionType::Debit,
                'reference'       => $utx['ref'],
            ]);
        }

        $owner = $pack['users'][0];
        $this->command?->info("  ✓ {$pack['org']['name']} [{$locale}] — login: {$owner['email']} / password");
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function createPostedExpense(
        Organization $org,
        array $data,
        Carbon $date,
        ?VatRate $vatRate,
        BankAccount $bankAccount,
        int &$bankTxNum,
    ): void {
        $vatAmount = $vatRate ? round($data['amount'] * ($vatRate->rate / 100), 2) : 0;

        $expense = Expense::create([
            'organization_id' => $org->id,
            'vat_rate_id'     => $vatRate?->id,
            'category'        => $data['category'],
            'description'     => $data['desc'],
            'amount'          => $data['amount'],
            'vat_amount'      => $vatAmount,
            'date'            => $date,
            'vendor'          => $data['vendor'],
            'status'          => ExpenseStatus::Pending->value,
            'currency'        => 'CHF',
        ]);

        $this->approveExpense->execute($expense);
        $this->postExpense->execute($expense, $data['account']);

        $bankTxNum++;
        BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'date'            => $date,
            'description'     => $data['desc'],
            'amount'          => $data['amount'],
            'type'            => BankTransactionType::Debit,
            'reference'       => sprintf('EXP-%04d-%03d', $date->year, $bankTxNum),
        ]);
    }
}
