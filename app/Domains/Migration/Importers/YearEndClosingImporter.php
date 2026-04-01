<?php

namespace App\Domains\Migration\Importers;

use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\ValidationResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Collection;

/**
 * Imports year-end closing data as opening balances for the current fiscal year.
 *
 * Delegates to {@see OpeningBalanceImporter} since the data structure is identical.
 */
class YearEndClosingImporter implements DataTypeImporterInterface
{
    public function __construct(
        private readonly OpeningBalanceImporter $openingBalanceImporter,
    ) {}

    public function dataType(): DataType
    {
        return DataType::YearEndClosing;
    }

    public function dependencies(): array
    {
        return [DataType::Accounts, DataType::OpeningBalances];
    }

    public function validate(Collection $rows, Organization $organization): ValidationResult
    {
        return $this->openingBalanceImporter->validate($rows, $organization);
    }

    public function import(Collection $rows, Organization $organization): ImportResult
    {
        $result = $this->openingBalanceImporter->import($rows, $organization);

        // Re-wrap with our data type
        return $result->success
            ? ImportResult::success($this->dataType(), $result->importedCount, $result->skippedCount, $result->warnings)
            : ImportResult::failure($this->dataType(), $result->errors, $result->importedCount, $result->failedCount);
    }
}
