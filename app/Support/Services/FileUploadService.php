<?php

namespace App\Support\Services;

use App\Support\Exceptions\FileUploadFailedException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles file uploads to local or cloud storage with validation.
 */
class FileUploadService
{
    /**
     * @throws FileUploadFailedException when the disk write fails (disk full,
     *                                   permissions, unreachable cloud storage, ...). Previously this silently
     *                                   returned an empty string path on failure, which got persisted as a
     *                                   broken file reference with no visible error.
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'local'): string
    {
        // Use the MIME-detected extension rather than the client-supplied name.
        // getClientOriginalExtension() trusts user input — an attacker could set the
        // extension to .php, .phar, etc. guessExtension() derives it from actual content.
        $extension = $file->guessExtension() ?? strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid().'.'.$extension;

        $path = $file->storeAs($directory, $filename, $disk);

        if ($path === false) {
            throw new FileUploadFailedException;
        }

        return $path;
    }

    public function delete(?string $path, string $disk = 'local'): void
    {
        if ($path) {
            Storage::disk($disk)->delete($path);
        }
    }
}
