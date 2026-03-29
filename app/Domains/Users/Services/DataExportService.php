<?php

namespace App\Domains\Users\Services;

use App\Domains\Users\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * GDPR data export service (Art. 15 & Art. 20).
 *
 * Exports all personal data held for a user as a structured array
 * suitable for JSON download or archival.
 */
class DataExportService
{
    /**
     * Export all personal data for a user as a structured array (GDPR Art. 15 + Art. 20).
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $data = [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
                'accepted_privacy_at' => $user->accepted_privacy_at?->toIso8601String(),
                'accepted_terms_at' => $user->accepted_terms_at?->toIso8601String(),
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            ],
            'organizations' => [],
            'activity_log' => [],
        ];

        // Organizations and their data
        foreach ($user->organizations as $org) {
            $orgData = [
                'name' => $org->name,
                'legal_name' => $org->legal_name,
                'address' => $org->address,
                'city' => $org->city,
                'postal_code' => $org->postal_code,
                'canton' => $org->canton,
                'country' => $org->country,
                'vat_number' => $org->vat_number,
                'currency' => $org->currency,
                'role' => $org->pivot->role,
                'customers' => [],
                'suppliers' => [],
                'invoices' => [],
                'expenses' => [],
                'bank_accounts' => [],
            ];

            if ($org->relationLoaded('customers') || method_exists($org, 'customers')) {
                $orgData['customers'] = $org->customers()
                    ->withTrashed()
                    ->get(['name', 'email', 'phone', 'address', 'city', 'postal_code', 'country', 'vat_number', 'created_at'])
                    ->toArray();
            }

            if ($org->relationLoaded('suppliers') || method_exists($org, 'suppliers')) {
                $orgData['suppliers'] = $org->suppliers()
                    ->withTrashed()
                    ->get(['name', 'email', 'phone', 'address', 'city', 'postal_code', 'country', 'vat_number', 'iban', 'created_at'])
                    ->toArray();
            }

            if (method_exists($org, 'invoices')) {
                $orgData['invoices'] = $org->invoices()
                    ->withTrashed()
                    ->get(['invoice_number', 'status', 'issue_date', 'due_date', 'total', 'currency', 'created_at'])
                    ->toArray();
            }

            if (method_exists($org, 'expenses')) {
                $orgData['expenses'] = $org->expenses()
                    ->withTrashed()
                    ->get(['description', 'amount', 'currency', 'expense_date', 'status', 'category', 'created_at'])
                    ->toArray();
            }

            if (method_exists($org, 'bankAccounts')) {
                $orgData['bank_accounts'] = $org->bankAccounts()
                    ->withTrashed()
                    ->get(['name', 'iban', 'currency', 'created_at'])
                    ->toArray();
            }

            $data['organizations'][] = $orgData;
        }

        // Activity log entries for this user
        $data['activity_log'] = Activity::where('causer_type', User::class)
            ->where('causer_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get(['description', 'subject_type', 'event', 'properties', 'created_at'])
            ->toArray();

        return $data;
    }
}
