<?php

namespace App\Console\Commands;

use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Services\ArchiveAccessLog;
use App\Domains\Accounting\Services\ArchivePackageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('archive:package {operation : prepare, verify, export, resume or inspect} {package : Package UUID} {--output= : New ZIP path for export}')]
#[Description('Operate on a frozen archive package by UUID, without an EE account')]
class ArchivePackageCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ArchivePackageService $packages, ArchiveAccessLog $audit): int
    {
        try {
            $package = ArchivePackage::withoutGlobalScopes()->findOrFail($this->argument('package'));
            switch ($this->argument('operation')) {
                case 'prepare':
                    $packages->prepare($package, $package->archival_pdf);
                    break;
                case 'verify':
                    $result = $packages->verify($package);
                    $audit->record($package->organization_id, $package->id, 'package.integrity_checked', null, ['channel' => 'cli', ...$result]);
                    $this->line(json_encode($result, JSON_THROW_ON_ERROR));

                    return $result['valid'] ? self::SUCCESS : self::FAILURE;
                case 'export':
                    $output = $this->option('output');
                    if (! is_string($output) || $output === '' || file_exists($output)) {
                        throw new \InvalidArgumentException('Export requires --output pointing to a new file.');
                    }
                    $source = $packages->export($package);
                    try {
                        $destination = fopen($output, 'xb');
                        if ($destination === false) {
                            throw new \RuntimeException('Cannot create export file.');
                        }
                        $input = fopen($source, 'rb');
                        try {
                            if ($input === false || stream_copy_to_stream($input, $destination) !== filesize($source)) {
                                throw new \RuntimeException('Incomplete export.');
                            }
                            fflush($destination);
                        } finally {
                            if (is_resource($input)) {
                                fclose($input);
                            }
                            fclose($destination);
                        }
                        $sourceHash = hash_file('sha256', $source);
                        $outputHash = hash_file('sha256', $output);
                        if ($sourceHash === false || $outputHash === false || ! hash_equals($sourceHash, $outputHash)) {
                            throw new \RuntimeException('Export integrity check failed.');
                        }
                    } finally {
                        unlink($source);
                    }
                    $audit->record($package->organization_id, $package->id, 'package.exported', null, ['channel' => 'cli']);
                    break;
                case 'resume':
                    $packages->retry($package);
                    $audit->record($package->organization_id, $package->id, 'package.retry_requested', null, ['channel' => 'cli']);
                    break;
                case 'inspect':
                    $audit->record($package->organization_id, $package->id, 'package.inspected', null, ['channel' => 'cli']);
                    $this->line($package->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                    return self::SUCCESS;
                default:
                    throw new \InvalidArgumentException('Choose prepare, verify, export, resume or inspect.');
            }
            $this->info($package->id.' '.$package->refresh()->state);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
