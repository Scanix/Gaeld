<?php

namespace App\Domains\Migration\DTOs;

use App\Domains\Migration\Contracts\ImportRowInterface;
use Illuminate\Support\Collection;

/**
 * Result of parsing a file via a platform parser.
 */
class ParseResult
{
    /**
     * @param  Collection<int, ImportRowInterface>  $rows
     * @param  string[]  $errors  Fatal parsing errors
     */
    public function __construct(
        public readonly Collection $rows,
        public readonly array $errors = [],
    ) {}

    public function isSuccessful(): bool
    {
        return empty($this->errors) && $this->rows->isNotEmpty();
    }

    /**
     * @return Collection<int, mixed>
     */
    public function validRows(): Collection
    {
        return $this->rows->filter(fn (ImportRowInterface $row) => $row->isValid());
    }

    /**
     * @return Collection<int, mixed>
     */
    public function invalidRows(): Collection
    {
        return $this->rows->reject(fn (ImportRowInterface $row) => $row->isValid());
    }

    public function totalCount(): int
    {
        return $this->rows->count();
    }

    public function validCount(): int
    {
        return $this->validRows()->count();
    }
}
