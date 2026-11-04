<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ArchiveAccessEvent;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\Enums\Role;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ArchiveAccessAuditTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    public function test_membership_change_is_not_committed_if_its_preservation_audit_fails(): void
    {
        $this->setUpOrganization();
        $member = User::factory()->create();
        $eventName = 'eloquent.creating: '.ArchiveAccessEvent::class;
        Event::listen($eventName, static function (): void {
            throw new \RuntimeException('Audit storage unavailable.');
        });
        try {
            app(OrganizationService::class)->addMember($this->organization, $member, 'viewer');
            $this->fail('Unaudited membership must not be committed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit storage unavailable.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }
        $this->assertFalse($this->organization->users()->where('users.id', $member->id)->exists());
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $member->id]);
    }

    public function test_role_changes_preserve_previous_and_new_access_rights(): void
    {
        $this->setUpOrganization();
        $member = User::factory()->create();
        $service = app(OrganizationService::class);
        $service->addMember($this->organization, $member, 'viewer');
        $service->changeMemberRole($this->organization, $member, Role::Member);
        $event = ArchiveAccessEvent::query()->where('action', 'access.role_changed')->firstOrFail();
        $this->assertSame('viewer', $event->metadata['previous_role']);
        $this->assertSame('member', $event->metadata['role']);
    }

    public function test_receipt_views_and_downloads_are_separately_retained(): void
    {
        $this->setUpOrganization();
        Storage::fake('local');
        $path = 'receipts/'.$this->organization->id.'/original.pdf';
        Storage::disk('local')->put($path, '%PDF-1.7 original');
        $expense = Expense::factory()->create(['organization_id' => $this->organization->id, 'receipt_path' => $path]);
        $this->actAsOrg()->get('/expenses/'.$expense->id.'/receipt?inline=1')->assertOk();
        $this->actAsOrg()->get('/expenses/'.$expense->id.'/receipt')->assertOk();

        foreach (['document.viewed', 'document.downloaded'] as $action) {
            $event = ArchiveAccessEvent::query()->where('action', $action)->sole();
            $this->assertSame($expense->id, $event->metadata['document_id']);
            $this->assertSame($this->organization->id, $event->organization_id);
        }
    }
}
