<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

/**
 * Phase 3: Legal Archives page restructured around fiscal-year accordions
 * with lazy-loaded per-year rows (replaces row-level pagination).
 */
class LegalArchiveIndexTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_index_returns_year_aggregates_sorted_desc(): void
    {
        $this->makeArchive(2024, verifiedAt: now());
        $this->makeArchive(2024, verifiedAt: null);
        $this->makeArchive(2025, verifiedAt: now());

        $response = $this->actAsOrg()->get('/accounting/archives');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Archives/Index')
            ->has('years', 2)
            ->where('years.0.fiscal_year', 2025)
            ->where('years.0.total_count', 1)
            ->where('years.0.verified_count', 1)
            ->where('years.1.fiscal_year', 2024)
            ->where('years.1.total_count', 2)
            ->where('years.1.verified_count', 1)
        );
    }

    public function test_for_year_returns_items_for_that_year_only(): void
    {
        $this->makeArchive(2024, documentType: 'invoice');
        $this->makeArchive(2024, documentType: 'expense');
        $this->makeArchive(2025, documentType: 'invoice');

        $response = $this->actAsOrg()->getJson('/accounting/archives/year/2024');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'items');
        $response->assertJsonPath('items.0.document_type', 'expense'); // alpha sort
        $response->assertJsonPath('items.1.document_type', 'invoice');
    }

    public function test_index_and_for_year_exclude_other_organizations(): void
    {
        $this->makeArchive(2025);

        $foreignOrg = Organization::factory()->create();
        LegalArchive::create([
            'organization_id' => $foreignOrg->id,
            'document_type' => 'invoice',
            'document_id' => Str::uuid()->toString(),
            'fiscal_year' => 2025,
            'checksum_sha256' => str_repeat('a', 64),
            'storage_path' => 'archives/foreign/x.zip',
            'archived_at' => now(),
            'expires_at' => now()->addYears(10),
        ]);

        $this->actAsOrg()->get('/accounting/archives')
            ->assertInertia(fn ($page) => $page->where('years.0.total_count', 1));

        $this->actAsOrg()->getJson('/accounting/archives/year/2025')
            ->assertJsonCount(1, 'items');
    }

    private function makeArchive(
        int $fiscalYear,
        string $documentType = 'invoice',
        ?\DateTimeInterface $verifiedAt = null,
    ): LegalArchive {
        return LegalArchive::create([
            'organization_id' => $this->organization->id,
            'document_type' => $documentType,
            'document_id' => Str::uuid()->toString(),
            'fiscal_year' => $fiscalYear,
            'checksum_sha256' => str_repeat('a', 64),
            'storage_path' => 'archives/'.$this->organization->id.'/'.Str::random(8).'.zip',
            'archived_at' => now()->setDate($fiscalYear, 12, 31),
            'expires_at' => now()->setDate($fiscalYear + 10, 12, 31),
            'verified_at' => $verifiedAt,
        ]);
    }
}
