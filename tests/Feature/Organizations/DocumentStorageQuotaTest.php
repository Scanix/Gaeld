<?php

namespace Tests\Feature\Organizations;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\OrganizationDocumentStorageService;
use App\Domains\Users\Models\User;
use App\Support\Contracts\OrganizationQuotaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentStorageQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_reservation_rejects_bytes_beyond_the_plan_limit(): void
    {
        $organization = Organization::factory()->create();
        $this->app->instance(OrganizationQuotaResolver::class, new class implements OrganizationQuotaResolver
        {
            public function maxUsers(Organization $organization): int
            {
                return -1;
            }

            public function maxOrganizations(User $user): int
            {
                return -1;
            }

            public function maxInvoicesPerMonth(Organization $organization): int
            {
                return -1;
            }

            public function maxOcrScansPerDay(Organization $organization): int
            {
                return -1;
            }

            public function maxOcrScansPerMonth(Organization $organization): int
            {
                return -1;
            }

            public function maxStorageBytes(Organization $organization): int
            {
                return 100;
            }
        });

        $storage = app(OrganizationDocumentStorageService::class);
        $storage->reserve($organization, 60);

        try {
            $storage->reserve($organization, 41);
            self::fail('Expected the storage quota to reject the reservation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }

        $this->assertSame(60, $storage->bytesUsed($organization));

        $storage->release($organization, 60);

        $this->assertSame(0, $storage->bytesUsed($organization));
    }
}
