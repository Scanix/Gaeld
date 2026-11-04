<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackfillArchivePackages extends Command
{
    protected $signature = 'archive:backfill {--organization=} {--fiscal-year=} {--archival-pdf}';

    protected $description = 'Resume historical capture without asserting authenticity before the capture date';

    public function handle(): int
    {
        $this->warn('Historical backfills establish evidence from today; they do not establish historical integrity.');

        return $this->call('archive:capture', [
            '--organization' => $this->option('organization'), '--fiscal-year' => $this->option('fiscal-year'),
            '--archival-pdf' => $this->option('archival-pdf'), '--historical' => true,
        ]);
    }
}
