<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Actions\ImportAccountsAction;
use App\Domains\Accounting\Jobs\ExportChartOfAccountsJob;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Requests\ImportAccountsRequest;
use App\Domains\Accounting\Requests\StoreAccountRequest;
use App\Domains\Accounting\Requests\UpdateAccountRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * CRUD operations for chart-of-accounts entries.
 */
class AccountController extends Controller
{
    public function store(StoreAccountRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $orgId = $currentOrg->id();

        $validated = $request->validated();
        $validated['organization_id'] = $orgId;

        Account::create($validated);

        return redirect()->route('accounting.chart')
            ->with('success', __('app.account_created'));
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $validated = $request->validated();

        if (array_key_exists('code', $validated)) {
            $this->authorize('updateCode', $account);
        }

        $account->update($validated);

        return redirect()->route('accounting.chart')
            ->with('success', __('app.account_updated'));
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        $account->delete();

        return redirect()->route('accounting.chart')
            ->with('success', __('app.account_deleted'));
    }

    public function import(ImportAccountsRequest $request, CurrentOrganization $currentOrg, ImportAccountsAction $action): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $result = $action->parseFile($request->file('file'));

        if (! empty($result['errors'])) {
            return back()->withErrors(['file' => implode("\n", array_slice($result['errors'], 0, 20))]);
        }

        $action->execute($currentOrg->id(), $result['rows'], $request->input('mode', 'merge'));

        return redirect()->route('accounting.chart')
            ->with('success', __('app.import_success'));
    }

    public function export(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('viewAny', Account::class);

        $format = $request->input('format', 'csv');

        ExportChartOfAccountsJob::dispatch(
            $currentOrg->id(),
            (string) $request->user()->id,
            $format,
        );

        return redirect()->route('accounting.chart')
            ->with('success', __('app.export_dispatched'));
    }

    public function downloadExport(Request $request): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $path = $request->query('path', '');

        $absolutePath = Storage::disk('local')->path('exports/'.basename($path));

        abort_unless(file_exists($absolutePath), 404);

        return response()->download($absolutePath);
    }
}
