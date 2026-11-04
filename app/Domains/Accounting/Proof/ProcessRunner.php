<?php

namespace App\Domains\Accounting\Proof;

use RuntimeException;
use Symfony\Component\Process\Process;

class ProcessRunner
{
    /** @param list<string> $arguments */
    public function run(array $arguments): string
    {
        $process = new Process($arguments);
        $process->setTimeout((float) config('archiving.process_timeout', 120));
        $process->run();
        if (! $process->isSuccessful()) {
            // Never log command arguments: provider credentials can be file paths.
            throw new RuntimeException('Archive evidence command failed: '.basename($arguments[0]).' (exit '.$process->getExitCode().').');
        }

        return $process->getOutput();
    }
}
