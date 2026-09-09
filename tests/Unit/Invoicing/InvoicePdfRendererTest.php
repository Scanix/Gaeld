<?php

namespace Tests\Unit\Invoicing;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoicePdfRenderer;
use App\Domains\Invoicing\Support\InvoicePdfStyle;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use TCPDF;
use Tests\TestCase;

class InvoicePdfRendererTest extends TestCase
{
    public function test_renders_sender_left_and_recipient_in_the_right_address_window(): void
    {
        $organization = new Organization([
            'name' => 'Sender GmbH',
            'legal_name' => 'Sender GmbH',
            'address' => 'Senderstrasse 1',
            'postal_code' => '8001',
            'city' => 'Zürich',
            'country' => 'CH',
        ]);
        $customer = new Contact([
            'name' => 'Recipient AG',
            'address' => 'Recipientstrasse 2',
            'postal_code' => '3000',
            'city' => 'Bern',
            'country' => 'CH',
        ]);
        $invoice = new Invoice([
            'type' => InvoiceType::Invoice,
            'issue_date' => Carbon::parse('2026-01-15'),
            'due_date' => Carbon::parse('2026-02-15'),
            'currency' => 'CHF',
        ]);
        $invoice->setRelation('customer', $customer);

        $tcpdf = new class extends TCPDF
        {
            /** @var array<int, array{x: float, y: float}> */
            public array $positions = [];

            public function setXY($x, $y, $rtloff = false): void
            {
                $this->positions[] = ['x' => (float) $x, 'y' => (float) $y];

                parent::setXY($x, $y, $rtloff);
            }
        };
        $tcpdf->setPrintHeader(false);
        $tcpdf->setPrintFooter(false);
        $tcpdf->AddPage();

        app(InvoicePdfRenderer::class)->renderInvoiceHeader($tcpdf, $invoice, $organization);

        $this->assertEquals(['x' => 15.0, 'y' => 15.0], $tcpdf->positions[0]);
        $this->assertEquals(['x' => 120.0, 'y' => 50.0], $tcpdf->positions[1]);
    }

    public function test_constrains_a_portrait_logo_before_rendering_the_sender_address(): void
    {
        Storage::fake('local');
        $logo = UploadedFile::fake()->image('portrait-logo.jpg', 100, 400);
        Storage::disk('local')->put('logos/portrait-logo.jpg', file_get_contents($logo->getPathname()));

        $organization = new Organization([
            'name' => 'Sender GmbH',
            'legal_name' => 'Sender GmbH',
            'address' => 'Senderstrasse 1',
            'postal_code' => '8001',
            'city' => 'Zürich',
            'country' => 'CH',
            'logo_path' => 'logos/portrait-logo.jpg',
        ]);
        $customer = new Contact([
            'name' => 'Recipient AG',
            'address' => 'Recipientstrasse 2',
            'postal_code' => '3000',
            'city' => 'Bern',
            'country' => 'CH',
        ]);
        $invoice = new Invoice([
            'type' => InvoiceType::Invoice,
            'issue_date' => Carbon::parse('2026-01-15'),
            'due_date' => Carbon::parse('2026-02-15'),
            'currency' => 'CHF',
        ]);
        $invoice->setRelation('customer', $customer);

        $tcpdf = new class extends TCPDF
        {
            /** @var array<int, array{w: float, h: float}> */
            public array $capturedImages = [];

            public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '', $align = '', $resize = false, $dpi = 300, $palign = '', $ismask = false, $imgmask = false, $border = 0, $fitbox = false, $hidden = false, $fitonpage = false, $alt = false, $altimgs = []): void
            {
                $this->capturedImages[] = ['w' => (float) $w, 'h' => (float) $h];
            }
        };
        $tcpdf->setPrintHeader(false);
        $tcpdf->setPrintFooter(false);
        $tcpdf->AddPage();

        app(InvoicePdfRenderer::class)->renderInvoiceHeader($tcpdf, $invoice, $organization);

        $this->assertCount(1, $tcpdf->capturedImages);
        $this->assertLessThanOrEqual(InvoicePdfStyle::LOGO_MAX_HEIGHT, $tcpdf->capturedImages[0]['h']);
        $this->assertGreaterThan(InvoicePdfStyle::MARGIN_TOP, $tcpdf->GetY());
    }

    public function test_renders_the_invoice_snapshot_instead_of_the_current_contact_address(): void
    {
        $organization = new Organization([
            'name' => 'Sender GmbH',
            'address' => 'Senderstrasse 1',
            'postal_code' => '8001',
            'city' => 'Zürich',
            'country' => 'CH',
        ]);
        $customer = new Contact([
            'name' => 'Recipient AG',
            'address' => 'New Street 9',
            'postal_code' => '8000',
            'city' => 'Zürich',
            'country' => 'CH',
        ]);
        $invoice = new Invoice([
            'type' => InvoiceType::Invoice,
            'issue_date' => Carbon::parse('2026-01-15'),
            'due_date' => Carbon::parse('2026-02-15'),
            'currency' => 'CHF',
            'customer_snapshot' => [
                'name' => 'Recipient AG',
                'email' => null,
                'address' => 'Old Street 1',
                'postal_code' => '1000',
                'city' => 'Lausanne',
                'country' => 'CH',
                'vat_number' => null,
            ],
        ]);
        $invoice->setRelation('customer', $customer);

        $tcpdf = new class extends TCPDF
        {
            /** @var array<int, string> */
            public array $cells = [];

            public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'M'): void
            {
                $this->cells[] = (string) $txt;

                parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $stretch, $ignore_min_height, $calign, $valign);
            }
        };
        $tcpdf->setPrintHeader(false);
        $tcpdf->setPrintFooter(false);
        $tcpdf->AddPage();

        app(InvoicePdfRenderer::class)->renderInvoiceHeader($tcpdf, $invoice, $organization);

        $this->assertContains('Old Street 1', $tcpdf->cells);
        $this->assertContains('1000 Lausanne', $tcpdf->cells);
        $this->assertNotContains('New Street 9', $tcpdf->cells);
        $this->assertNotContains('8000 Zürich', $tcpdf->cells);
    }

    public function test_renders_a_light_gald_copyright_footer(): void
    {
        $tcpdf = new class extends TCPDF
        {
            /** @var array<int, string> */
            public array $cells = [];

            public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'M'): void
            {
                $this->cells[] = (string) $txt;

                parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $stretch, $ignore_min_height, $calign, $valign);
            }
        };
        $tcpdf->setPrintHeader(false);
        $tcpdf->setPrintFooter(false);
        $tcpdf->AddPage();

        app(InvoicePdfRenderer::class)->renderFooter($tcpdf);

        $this->assertContains('© '.now()->year.' Gäld', $tcpdf->cells);
    }
}
