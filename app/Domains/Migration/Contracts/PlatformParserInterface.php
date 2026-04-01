<?php

namespace App\Domains\Migration\Contracts;

use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\Platform;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Contract for platform-specific file parsers.
 *
 * Each implementation knows how to read export files from a single
 * accounting platform (Bexio, Banana, Abacus, …) and normalize them
 * into {@see ImportRowInterface} DTOs for the import pipeline.
 *
 * Adding a new platform requires only a new implementation of this
 * interface — no changes to the orchestration code.
 */
interface PlatformParserInterface
{
    /**
     * The platform enum value this parser handles.
     */
    public function platform(): Platform;

    /**
     * Human-readable label translation key.
     */
    public function labelKey(): string;

    /**
     * Description translation key.
     */
    public function descriptionKey(): string;

    /**
     * The data types this parser can extract from its files.
     *
     * @return DataType[]
     */
    public function supportedDataTypes(): array;

    /**
     * File extensions accepted by this parser (e.g. ['csv', 'xls', 'xlsx']).
     *
     * @return string[]
     */
    public function acceptedExtensions(): array;

    /**
     * Parse the uploaded file and extract rows for the given data type.
     *
     * @return Collection<int, ImportRowInterface>
     */
    public function parse(UploadedFile $file, DataType $dataType): Collection;

    /**
     * Attempt to auto-detect which data type the file contains.
     * Returns null if detection is not possible.
     */
    public function detectDataType(UploadedFile $file): ?DataType;
}
