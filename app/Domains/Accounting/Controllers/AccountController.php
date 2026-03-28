<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Actions\ImportAccountsAction;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Requests\ImportAccountsRequest;
use App\Domains\Accounting\Requests\StoreAccountRequest;
use App\Domains\Accounting\Requests\UpdateAccountRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function import(ImportAccountsRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $content = $file->get();
        $orgId = $currentOrg->id();

        $action = new ImportAccountsAction;

        if ($extension === 'json') {
            $rows = json_decode($content, true);
            if (! is_array($rows)) {
                return back()->withErrors(['file' => __('app.import_validation_error')]);
            }
        } else {
            $rows = $action->parseCsv($content);
        }

        if (empty($rows)) {
            return back()->withErrors(['file' => __('app.import_validation_error')]);
        }

        $errors = $action->validate($rows);

        if (! empty($errors)) {
            return back()->withErrors(['file' => implode("\n", array_slice($errors, 0, 20))]);
        }

        $action->execute($orgId, $rows, $request->input('mode', 'merge'));

        return redirect()->route('accounting.chart')
            ->with('success', __('app.import_success'));
    }

    public function export(Request $request, CurrentOrganization $currentOrg): StreamedResponse
    {
        $this->authorize('viewAny', Account::class);

        $format = $request->input('format', 'csv');
        $accounts = Account::where('organization_id', $currentOrg->id())
            ->orderBy('code')
            ->get(['code', 'name', 'type', 'description', 'is_active']);

        if ($format === 'json') {
            return response()->streamDownload(function () use ($accounts) {
                echo $accounts->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }, 'chart_of_accounts.json', [
                'Content-Type' => 'application/json',
            ]);
        }

        return response()->streamDownload(function () use ($accounts) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['code', 'name', 'type', 'description', 'is_active']);
            foreach ($accounts as $account) {
                fputcsv($handle, [
                    $account->code,
                    $account->name,
                    $account->type->value,
                    $account->description,
                    $account->is_active ? '1' : '0',
                ]);
            }
            fclose($handle);
        }, 'chart_of_accounts.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
