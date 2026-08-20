<?php

namespace Tests\Unit\Services;

use App\Support\Exceptions\FileUploadFailedException;
use App\Support\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression coverage for App\Support\Services\FileUploadService::store().
 *
 * Previously the method was typed to return `string` but delegated to
 * UploadedFile::storeAs(), which returns `string|false`. Without
 * declare(strict_types=1), PHP coerces a `false` return into an empty
 * string `""` rather than raising a TypeError — so a failed disk write
 * silently persisted an empty path instead of surfacing an error.
 */
class FileUploadServiceTest extends TestCase
{
    public function test_store_returns_the_path_on_success(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf');

        $path = (new FileUploadService)->store($file, 'receipts/org-1');

        $this->assertStringStartsWith('receipts/org-1/', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_store_throws_instead_of_silently_returning_an_empty_path_on_failure(): void
    {
        $fake = UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf');

        $failingFile = new class($fake->getPathname(), $fake->getClientOriginalName(), $fake->getClientMimeType(), null, true) extends UploadedFile
        {
            public function storeAs($path, $name = null, $options = []): string|false
            {
                return false;
            }
        };

        $this->expectException(FileUploadFailedException::class);

        (new FileUploadService)->store($failingFile, 'receipts/org-1');
    }
}
