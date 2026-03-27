<?php

namespace Tests\Feature;

use App\Domains\Expenses\Contracts\ReceiptOcrInterface;
use App\Domains\Expenses\DTOs\ReceiptOcrResult;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Jobs\ProcessReceiptOcrJob;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class ScanReceiptTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private Organization $org;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->org = Organization::create([
            'name' => 'Test GmbH',
            'currency' => 'CHF',
        ]);
        $this->org->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $this->org, 'owner');

        // Mock the OCR service to avoid Tesseract dependency in tests
        $this->app->bind(ReceiptOcrInterface::class, function () {
            return new class implements ReceiptOcrInterface
            {
                public function extract(string $imagePath): ReceiptOcrResult
                {
                    return new ReceiptOcrResult(
                        rawText: "Migros\n15.03.2025\nTotal CHF 42.50",
                        amount: 42.50,
                        date: '2025-03-15',
                        vendor: 'Migros',
                        confidence: null,
                    );
                }
            };
        });
    }

    public function test_scan_receipt_dispatches_job_and_returns_scan_id(): void
    {
        Storage::fake('local');
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->postJson('/expenses/scan-receipt', [
                'receipt' => UploadedFile::fake()->image('receipt.jpg', 800, 600),
            ]);

        $response->assertOk()
            ->assertJsonStructure(['scan_id', 'receipt_path']);

        $scanId = $response->json('scan_id');
        $receiptPath = $response->json('receipt_path');

        // Verify the job was dispatched
        Queue::assertPushed(ProcessReceiptOcrJob::class, function ($job) use ($scanId, $receiptPath) {
            return $job->scanId === $scanId && $job->receiptPath === $receiptPath;
        });

        // Verify receipt file was stored
        Storage::disk('local')->assertExists($receiptPath);

        // Verify cache has processing status
        $cached = Cache::get("receipt_scan:{$scanId}");
        $this->assertEquals('processing', $cached['status']);
    }

    public function test_scan_receipt_status_returns_completed_after_job_runs(): void
    {
        Storage::fake('local');

        // Upload the receipt (run the job synchronously via sync driver)
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->postJson('/expenses/scan-receipt', [
                'receipt' => UploadedFile::fake()->image('receipt.jpg', 800, 600),
            ]);

        $scanId = $response->json('scan_id');

        // Simulate what the job does: update cache with completed status
        Cache::put("receipt_scan:{$scanId}", [
            'status' => 'completed',
            'receipt_path' => $response->json('receipt_path'),
            'extracted' => [
                'amount' => 42.50,
                'date' => '2025-03-15',
                'vendor' => 'Migros',
                'raw_text' => "Migros\n15.03.2025\nTotal CHF 42.50",
                'confidence' => null,
            ],
        ], now()->addMinutes(30));

        // Poll for status
        $statusResponse = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->getJson("/expenses/scan-receipt/{$scanId}");

        $statusResponse->assertOk()
            ->assertJson([
                'status' => 'completed',
                'extracted' => [
                    'amount' => 42.50,
                    'date' => '2025-03-15',
                    'vendor' => 'Migros',
                ],
            ]);
    }

    public function test_scan_receipt_status_returns_not_found_for_unknown_id(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->getJson('/expenses/scan-receipt/nonexistent-uuid');

        $response->assertNotFound()
            ->assertJson(['status' => 'not_found']);
    }

    public function test_scan_receipt_rejects_non_image_files(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->postJson('/expenses/scan-receipt', [
                'receipt' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['receipt']);
    }

    public function test_scan_receipt_rejects_oversized_files(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->postJson('/expenses/scan-receipt', [
                'receipt' => UploadedFile::fake()->image('huge.jpg')->size(11000),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['receipt']);
    }

    public function test_scan_receipt_requires_authentication(): void
    {
        $response = $this->postJson('/expenses/scan-receipt', [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertUnauthorized();
    }

    public function test_create_expense_with_receipt_path(): void
    {
        Storage::fake('local');

        // First, scan the receipt to get a stored path
        $scanResponse = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->postJson('/expenses/scan-receipt', [
                'receipt' => UploadedFile::fake()->image('receipt.jpg', 800, 600),
            ]);

        $receiptPath = $scanResponse->json('receipt_path');

        // Now create an expense using the receipt_path (no file re-upload)
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->post('/expenses', [
                'category' => 'Office Supplies',
                'amount' => '42.50',
                'date' => '2025-03-15',
                'vendor' => 'Migros',
                'receipt_path' => $receiptPath,
            ]);

        $response->assertRedirect();

        $expense = Expense::first();
        $this->assertNotNull($expense);
        $this->assertEquals('42.50', $expense->amount);
        $this->assertEquals('Migros', $expense->vendor);
        $this->assertEquals($receiptPath, $expense->receipt_path);
        $this->assertEquals(ExpenseStatus::Pending, $expense->status);
    }

    public function test_create_expense_rejects_receipt_path_from_other_org(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'currency' => 'CHF']);

        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->post('/expenses', [
                'category' => 'Office Supplies',
                'amount' => '10.00',
                'date' => '2025-03-15',
                'receipt_path' => "receipts/{$otherOrg->id}/malicious.jpg",
            ]);

        // The receipt_path should be silently ignored (not matching current org)
        $response->assertRedirect();
        $expense = Expense::first();
        $this->assertNull($expense->receipt_path);
    }

    public function test_ocr_job_stores_result_in_cache(): void
    {
        Storage::fake('local');
        $scanId = 'test-scan-id';
        $receiptPath = 'receipts/test-org/receipt.jpg';

        // Create a fake image file
        Storage::disk('local')->put($receiptPath, 'fake-image-content');

        $job = new ProcessReceiptOcrJob($scanId, $receiptPath);
        $job->handle(app(ReceiptOcrInterface::class));

        $cached = Cache::get("receipt_scan:{$scanId}");
        $this->assertNotNull($cached);
        $this->assertEquals('completed', $cached['status']);
        $this->assertEquals($receiptPath, $cached['receipt_path']);
        $this->assertEquals(42.50, $cached['extracted']['amount']);
        $this->assertEquals('2025-03-15', $cached['extracted']['date']);
        $this->assertEquals('Migros', $cached['extracted']['vendor']);
    }
}
