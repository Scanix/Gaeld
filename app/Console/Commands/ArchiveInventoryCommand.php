<?php

namespace App\Console\Commands;

use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[Signature('archive:inventory')]
#[Description('Print a recovery checkpoint covering every protected catalog version in this installation')]
class ArchiveInventoryCommand extends Command
{
    public function handle(ProtectedArchiveStorage $storage): int
    {
        $catalogs = [];
        foreach ($storage->inventory() as $entry) {
            $catalogs[] = $entry['catalog'];
        }
        if ($catalogs === []) {
            $this->components->error('No protected catalog versions were found.');

            return self::FAILURE;
        }
        $this->output->writeln(json_encode([
            'schema' => 'gaeld-recovery-checkpoint/1',
            'observed_at' => now()->toIso8601String(),
            'bucket' => config('archiving.s3.bucket'),
            'prefix' => trim((string) config('archiving.s3.prefix'), '/'),
            'catalogs' => $catalogs,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
