<?php

namespace Tests\Feature\Expenses;

use App\Domains\Expenses\Enums\ReceiptScanStatus;
use App\Domains\Expenses\Models\ReceiptScan;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

/**
 * Phase 2 step 2.8: Pending OCR receipt-scans index page (destination of
 * the dashboard OCR card) plus the discard endpoint.
 */
class ReceiptScanIndexTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_index_renders_pending_scans_for_current_organization(): void
    {
        $pending = $this->makeScan(ReceiptScanStatus::Pending, now()->addHours(24));
        $completed = $this->makeScan(ReceiptScanStatus::Completed, now()->addHours(12));

        $response = $this->actAsOrg()->get('/expenses/receipt-scans');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Expenses/ReceiptScans/Index')
            ->has('scans', 2)
            ->where('scans.0.scan_id', $completed->scan_id) // newest first
            ->where('scans.1.scan_id', $pending->scan_id)
        );
    }

    public function test_index_excludes_expired_validated_and_failed_scans(): void
    {
        $this->makeScan(ReceiptScanStatus::Pending, now()->subHour()); // expired
        $this->makeScan(ReceiptScanStatus::Validated, now()->addDay()); // used
        $this->makeScan(ReceiptScanStatus::Failed, now()->addDay()); // failed

        $response = $this->actAsOrg()->get('/expenses/receipt-scans');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Expenses/ReceiptScans/Index')
            ->has('scans', 0)
        );
    }

    public function test_discard_endpoint_expires_scan_and_redirects(): void
    {
        $scan = $this->makeScan(ReceiptScanStatus::Pending, now()->addHours(24));

        $response = $this->actAsOrg()->delete("/expenses/receipt-scans/{$scan->scan_id}");

        $response->assertRedirect(route('expenses.receipt-scans.index'));
        $scan->refresh();
        $this->assertTrue($scan->expires_at->isPast());
    }

    public function test_discard_endpoint_rejects_other_organizations_scans(): void
    {
        $foreignOrg = Organization::factory()->create();
        $scanId = Str::uuid()->toString();
        ReceiptScan::create([
            'organization_id' => $foreignOrg->id,
            'user_id' => $this->user->id,
            'scan_id' => $scanId,
            'receipt_path' => 'receipts/foreign/x.pdf',
            'status' => ReceiptScanStatus::Pending->value,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->actAsOrg()->delete("/expenses/receipt-scans/{$scanId}");

        $response->assertNotFound();
    }

    private function makeScan(ReceiptScanStatus $status, \DateTimeInterface $expiresAt): ReceiptScan
    {
        return ReceiptScan::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'scan_id' => Str::uuid()->toString(),
            'receipt_path' => 'receipts/'.$this->organization->id.'/'.Str::random(8).'.pdf',
            'status' => $status->value,
            'expires_at' => $expiresAt,
        ]);
    }
}
