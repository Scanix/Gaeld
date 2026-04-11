<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Imports chart-of-accounts entries from an uploaded CSV or JSON file.
 */
class ImportAccountsAction
{
    /**
     * Import accounts from an uploaded file (CSV or JSON).
     *
     * @return array{rows: array<array<string, mixed>>, errors: array<string>}
     */
    public function parseFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $content = $file->get();

        if ($content === false) {
            return ['rows' => [], 'errors' => [__('app.import_validation_error')]];
        }

        if ($extension === 'json') {
            $rows = json_decode($content, true);
            if (! is_array($rows)) {
                return ['rows' => [], 'errors' => [__('app.import_validation_error')]];
            }
        } else {
            $rows = $this->parseCsv($content);
        }

        if (empty($rows)) {
            return ['rows' => [], 'errors' => [__('app.import_validation_error')]];
        }

        $errors = $this->validate($rows);

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  array<array<string, mixed>>  $rows
     * @return array<string> validation errors (empty on success)
     */
    public function validate(array $rows): array
    {
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

        // Check for duplicate codes within the import file
        $codes = array_column($rows, 'code');
        if (count($codes) !== count(array_unique($codes))) {
            $errors[] = 'Import file contains duplicate account codes.';
        }

        return $errors;
    }

    /**
     * @param  array<array<string, mixed>>  $rows
     * @return Collection<int, Account>
     */
    public function execute(string $orgId, array $rows, string $mode): Collection
    {
        return DB::transaction(function () use ($orgId, $rows, $mode): Collection {
            if ($mode === 'replace') {
                Account::where('organization_id', $orgId)
                    ->where('is_system', false)
                    ->whereDoesntHave('transactionLines')
                    ->delete();
            }

            $accounts = collect();

            foreach ($rows as $row) {
                $accounts->push(Account::updateOrCreate(
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
                ));
            }

            return $accounts;
        });
    }

    /**
     * Parse CSV content into an array of rows keyed by header names.
     *
     * @return array<array<string, mixed>>|null null on parse failure
     */
    public function parseCsv(string $content): ?array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));
        if (count($lines) < 2) {
            return null;
        }

        $headers = str_getcsv(array_shift($lines));
        $headers = array_map('strtolower', array_map('trim', $headers));

        if (array_diff(['code', 'name', 'type'], $headers)) {
            return null;
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
