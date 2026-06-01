<?php

namespace App\Domains\Organizations\Actions;

use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Soft-deletes an organization and cleans up related authentication
 * state. Used by SaaS admin "delete org" action and by the spam
 * cleanup command.
 *
 * What this does:
 *  - Soft-deletes the organization (sets `deleted_at`).
 *  - Detaches all users from the `organization_users` pivot.
 *  - Soft-deletes the EE subscription if one exists.
 *
 * User records are intentionally left intact — they have no SoftDeletes
 * trait today, and we don't want to risk cascading FK errors from
 * unrelated tables. Orphaned users land on the onboarding screen on
 * their next login, which is the same path real users take.
 *
 * Domain rows scoped to the organization (invoices, expenses, etc.) are
 * NOT touched. The soft-deleted org still owns them via foreign keys so
 * data can be recovered by un-soft-deleting the row if needed. Hard
 * deletion of related rows should go through a dedicated, audited
 * "purge" command — never call it automatically here.
 */
class DeleteOrganizationAction
{
    public function execute(Organization $organization, ?string $reason = null): void
    {
        DB::transaction(function () use ($organization, $reason) {
            $orgId = $organization->getKey();

            $detached = DB::table('organization_users')
                ->where('organization_id', $orgId)
                ->delete();

            // Soft-delete the EE subscription if the table exists.
            if (DB::getSchemaBuilder()->hasTable('ee_subscriptions')) {
                $hasDeletedAt = DB::getSchemaBuilder()->hasColumn('ee_subscriptions', 'deleted_at');

                if ($hasDeletedAt) {
                    DB::table('ee_subscriptions')
                        ->where('organization_id', $orgId)
                        ->whereNull('deleted_at')
                        ->update(['deleted_at' => now()]);
                } else {
                    DB::table('ee_subscriptions')->where('organization_id', $orgId)->delete();
                }
            }

            $organization->delete();

            Log::info('organization.deleted', [
                'organization_id' => $orgId,
                'memberships_detached' => $detached,
                'reason' => $reason,
            ]);
        });
    }
}
