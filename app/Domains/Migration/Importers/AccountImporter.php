<?php

namespace App\Domains\Migration\Importers;

use App\Domains\Accounting\Actions\ImportAccountsAction;
use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\Contracts\ImportRowInterface;
use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\ValidationResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Collection;

class AccountImporter implements DataTypeImporterInterface
{
    public function __construct(
        private readonly ImportAccountsAction $importAction,
    ) {}

    public function dataType(): DataType
    {
        return DataType::Accounts;
    }

    public function dependencies(): array
    {
        return [];
    }

    public function validate(Collection $rows, Organization $organization): ValidationResult
    {
        $arrayRows = $rows->filter(fn (ImportRowInterface $r) => $r->isValid())
            ->map(fn (ImportRowInterface $r) => $r->toArray())
            ->values()
            ->all();

        $errors = $this->importAction->validate($arrayRows);

        if (! empty($errors)) {
            return ValidationResult::failure($errors);
        }

        return ValidationResult::success();
    }

    public function import(Collection $rows, Organization $organization): ImportResult
    {
        $arrayRows = $rows->filter(fn (ImportRowInterface $r) => $r->isValid())
            ->map(fn (ImportRowInterface $r) => $r->toArray())
            ->values()
            ->all();

        if (empty($arrayRows)) {
            return ImportResult::failure($this->dataType(), ['No valid rows to import']);
        }

        $this->importAction->execute($organization->id, $arrayRows, 'merge');

        return ImportResult::success(
            $this->dataType(),
            count($arrayRows),
            $rows->count() - count($arrayRows),
        );
    }
}
