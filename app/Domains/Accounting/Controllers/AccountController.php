<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function store(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $orgId = $currentOrg->id();

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('accounts', 'code')->where('organization_id', $orgId),
            ],
            'name' => 'required|string|max:255',
            'type' => ['required', new Enum(AccountType::class)],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('organization_id', $orgId),
            ],
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['organization_id'] = $orgId;

        Account::create($validated);

        return redirect()->route('accounting.chart')
            ->with('success', __('app.account_created'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $hasTransactions = $account->transactionLines()->exists();

        $rules = [
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:1000',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('organization_id', $account->organization_id),
                Rule::notIn([$account->id]),
            ],
        ];

        if (! $hasTransactions) {
            $rules['code'] = [
                'required',
                'string',
                'max:20',
                Rule::unique('accounts', 'code')
                    ->where('organization_id', $account->organization_id)
                    ->ignore($account->id),
            ];
            $rules['type'] = ['required', new Enum(AccountType::class)];
        }

        $validated = $request->validate($rules);

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

    public function import(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $request->validate([
            'file' => 'required|file|mimes:'.config('uploads.allowed_mimes.import').'|max:'.config('uploads.max_size.import'),
            'mode' => 'required|in:add,replace',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $content = $file->get();
        $orgId = $currentOrg->id();

        if ($extension === 'json') {
            $rows = json_decode($content, true);
            if (! is_array($rows)) {
                return back()->withErrors(['file' => __('app.import_validation_error')]);
            }
        } else {
            $rows = $this->parseCsv($content);
        }

        if (empty($rows)) {
            return back()->withErrors(['file' => __('app.import_validation_error')]);
        }

        $validTypes = array_column(AccountType::cases(), 'value');
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 1;
            $rowValidator = Validator::make($row, [
                'code' => 'required|string|max:20',
                'name' => 'required|string|max:255',
                'type' => ['required', Rule::in($validTypes)],
                'description' => 'nullable|string|max:1000',
            ]);

            if ($rowValidator->fails()) {
                foreach ($rowValidator->errors()->all() as $msg) {
                    $errors[] = "Row {$line}: {$msg}";
                }
            }
        }

        if (! empty($errors)) {
            return back()->withErrors(['file' => implode("\n", array_slice($errors, 0, 20))]);
        }

        // Check for duplicate codes within the import file
        $codes = array_column($rows, 'code');
        if (count($codes) !== count(array_unique($codes))) {
            return back()->withErrors(['file' => __('app.import_validation_error')]);
        }

        DB::transaction(function () use ($rows, $orgId, $request) {
            if ($request->input('mode') === 'replace') {
                Account::where('organization_id', $orgId)
                    ->whereDoesntHave('transactionLines')
                    ->delete();
            }

            foreach ($rows as $row) {
                Account::updateOrCreate(
                    [
                        'organization_id' => $orgId,
                        'code' => $row['code'],
                    ],
                    [
                        'name' => $row['name'],
                        'type' => $row['type'],
                        'description' => $row['description'] ?? null,
                        'is_active' => $row['is_active'] ?? true,
                    ]
                );
            }
        });

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

    private function parseCsv(string $content): array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));
        if (count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));
        $headers = array_map('trim', $headers);
        $headers = array_map('strtolower', $headers);

        $required = ['code', 'name', 'type'];
        if (array_diff($required, $headers)) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            $values = str_getcsv($line);
            if (count($values) !== count($headers)) {
                continue;
            }
            $rows[] = array_combine($headers, $values);
        }

        return $rows;
    }
}
