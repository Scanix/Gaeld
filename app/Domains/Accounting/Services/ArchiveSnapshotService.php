<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Capture every dataset in one PostgreSQL statement and one MVCC snapshot. */
final class ArchiveSnapshotService
{
    /** @return array<string, mixed> */
    public function capture(Organization $organization, FiscalYear $period): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new \RuntimeException('Consistent archive snapshots require PostgreSQL.');
        }

        $org = $organization->id;
        $from = $period->start_date->toDateString();
        $to = $period->end_date->toDateString();
        $entries = fn (): Builder => DB::table('journal_entries')->where('organization_id', $org)->where('is_posted', true)->where('date', '<=', $to);
        $invoices = fn (): Builder => DB::table('invoices')->where('organization_id', $org)->whereBetween('issue_date', [$from, $to]);
        $expenses = fn (): Builder => DB::table('expenses')->where('organization_id', $org)->whereBetween('date', [$from, $to]);
        $bankAccounts = fn (): Builder => DB::table('bank_accounts')->where('organization_id', $org);
        $bankTransactions = fn (): Builder => DB::table('bank_transactions')->whereIn('bank_account_id', $bankAccounts()->select('id'))->whereBetween('date', [$from, $to]);
        $assets = fn (): Builder => DB::table('fixed_assets')->where('organization_id', $org)->where('purchase_date', '<=', $to);
        $salarySlips = fn (): Builder => DB::table('salary_slips')->where('organization_id', $org)
            ->whereRaw('(period_year * 100 + period_month) BETWEEN ? AND ?', [(int) $period->start_date->format('Ym'), (int) $period->end_date->format('Ym')]);

        $datasets = [
            'organization' => DB::table('organizations')->where('id', $org)->select(['id', 'name', 'legal_name', 'address', 'city', 'postal_code', 'canton', 'country', 'vat_number', 'currency']),
            'fiscal_year' => DB::table('fiscal_years')->where('id', $period->id),
            'accounts' => DB::table('accounts')->where('organization_id', $org),
            'journal_entries' => $entries(),
            'transaction_lines' => DB::table('transaction_lines')->whereIn('journal_entry_id', $entries()->select('id')),
            'invoices' => $invoices(),
            'invoice_lines' => DB::table('invoice_lines')->whereIn('invoice_id', $invoices()->select('id')),
            'invoice_payments' => DB::table('invoice_payments')->whereIn('invoice_id', $invoices()->select('id')),
            'expenses' => $expenses(),
            'contacts' => DB::table('contacts')->where('organization_id', $org),
            'cost_centers' => DB::table('cost_centers')->where('organization_id', $org),
            'lettrage_lots' => DB::table('lettrage_lots')->where('organization_id', $org),
            'bank_accounts' => $bankAccounts(),
            'bank_transactions' => $bankTransactions(),
            'bank_imports' => DB::table('bank_imports')->where('organization_id', $org)->whereIn('id', $bankTransactions()->select('bank_import_id')),
            'bank_matches' => DB::table('bank_matches')->whereIn('bank_transaction_id', $bankTransactions()->select('id')),
            'fixed_assets' => $assets(),
            'depreciation_entries' => DB::table('depreciation_entries')->whereIn('fixed_asset_id', $assets()->select('id'))->where('period_date', '<=', $to),
            'tax_declarations' => DB::table('tax_declarations')->where('organization_id', $org)->whereBetween('fiscal_year', [$period->start_date->year, $period->end_date->year]),
            'salary_slips' => $salarySlips(),
            'vat_entries' => DB::table('vat_entries')->whereIn('journal_entry_id', $entries()->where('date', '>=', $from)->select('id')),
            'vat_rates' => DB::table('vat_rates')->where('organization_id', $org),
            'legal_archives' => DB::table('legal_archives')->where('organization_id', $org)
                ->where(fn (Builder $query) => $query->where('fiscal_year_id', $period->id)->orWhere(fn (Builder $legacy) => $legacy->whereNull('fiscal_year_id')->where('fiscal_year', $period->start_date->year))),
            'issued_document_files' => DB::table('issued_document_files')->where('organization_id', $org)
                ->where(fn (Builder $query) => $query->where('fiscal_year_id', $period->id)
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('fiscal_year_id')->whereIn('document_type', ['invoice_pdf', 'supporting_invoice'])->whereIn('document_id', $invoices()->selectRaw('id::text')))
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('fiscal_year_id')->where('document_type', 'supporting_expense')->whereIn('document_id', $expenses()->selectRaw('id::text')))
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('fiscal_year_id')->where('document_type', 'salary_slip_pdf')->whereIn('document_id', $salarySlips()->selectRaw('id::text')))),
            'archive_access_events' => DB::table('archive_access_events')->where('organization_id', $org)
                ->whereNotIn('action', ['package.requested', 'package.prepared']),
            'journal_events' => DB::table('journal_events')->where('organization_id', $org)->whereIn('journal_entry_id', $entries()->select('id')),
        ];

        $parts = [];
        $bindings = [];
        foreach ($datasets as $name => $query) {
            $query->orderBy('id');
            // Numbers are represented as strings to preserve decimal precision through JSON consumers.
            $parts[] = "'{$name}', (SELECT COALESCE(jsonb_agg((SELECT jsonb_object_agg(k, CASE WHEN jsonb_typeof(v) = 'number' THEN to_jsonb(v::text) ELSE v END) FROM jsonb_each(to_jsonb(r)) e(k,v))), '[]'::jsonb) FROM ({$query->toSql()}) r)";
            array_push($bindings, ...$query->getBindings());
        }
        $record = DB::selectOne('SELECT jsonb_build_object('.implode(', ', $parts).')::text AS snapshot', $bindings);

        return json_decode($record->snapshot, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    }
}
