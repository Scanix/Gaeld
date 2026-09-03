<?php

namespace App\Console\Commands;

use App\Support\EditionReleasePair;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('edition:verify {--ee-version=}')]
#[Description('Verify the installed EE manifest against the CE compatibility contract')]
class VerifyEditionCompatibilityCommand extends Command
{
    public function handle(EditionReleasePair $releasePair): int
    {
        $manifestPath = base_path('plugins/gaeld-ee/plugin.json');
        if (! File::exists($manifestPath)) {
            $this->error('EE manifest is not installed.');

            return self::FAILURE;
        }

        try {
            /** @var array<string, mixed> $manifest */
            $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->error('EE manifest is invalid.');

            return self::FAILURE;
        }

        $metadata = $manifest['compatibility'] ?? null;
        if (! is_array($metadata)) {
            $this->error('EE manifest has no compatibility metadata.');

            return self::FAILURE;
        }

        /** @var array<string, mixed> $metadata */
        $expectedEeVersion = $this->option('ee-version');
        $expectedEeVersion = is_string($expectedEeVersion) && $expectedEeVersion !== ''
            ? $expectedEeVersion
            : null;
        $reason = $releasePair->failureReason($metadata, $expectedEeVersion);

        if ($reason !== null) {
            $this->error("Edition compatibility rejected: {$reason}");

            return self::FAILURE;
        }

        $this->info('Edition compatibility verified.');

        return self::SUCCESS;
    }
}
