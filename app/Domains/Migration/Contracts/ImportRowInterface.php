<?php

namespace App\Domains\Migration\Contracts;

/**
 * Normalized import row extracted from a platform-specific file.
 *
 * All platform parsers produce DTOs implementing this contract,
 * decoupling parsing logic from the import pipeline.
 */
interface ImportRowInterface
{
    /**
     * The row data as a flat associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * The 1-based row number from the source file (for error reporting).
     */
    public function sourceRow(): int;

    /**
     * Non-fatal warnings emitted during parsing (e.g. truncated values).
     *
     * @return string[]
     */
    public function warnings(): array;

    /**
     * Whether this row passed basic structural validation.
     */
    public function isValid(): bool;
}
