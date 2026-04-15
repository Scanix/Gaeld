<?php

namespace App\Support\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles file uploads to local or cloud storage with validation.
 */
class FileUploadService
{
    public function store(UploadedFile $file, string $directory, string $disk = 'local'): string
    {
        // Use the MIME-detected extension rather than the client-supplied name.
        // getClientOriginalExtension() trusts user input — an attacker could set the
        // extension to .php, .phar, etc. guessExtension() derives it from actual content.
        $extension = $file->guessExtension() ?? strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid().'.'.$extension;

        return $file->storeAs($directory, $filename, $disk);
    }

    public function delete(?string $path, string $disk = 'local'): void
    {
        if ($path) {
            Storage::disk($disk)->delete($path);
        }
    }
}
