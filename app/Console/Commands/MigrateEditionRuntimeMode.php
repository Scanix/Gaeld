<?php

namespace App\Console\Commands;

use App\Support\EditionCompatibility;
use App\Support\EditionReleasePair;
use App\Support\EditionRuntimeMode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('edition:migrate {--dry-run} {--mode=} {--force}')]
#[Description('Record the installation runtime mode without deleting edition data')]
class MigrateEditionRuntimeMode extends Command
{
    public function handle(EditionReleasePair $releasePair): int
    {
        $eeTables = $this->eeTables();
        $hasEeSchema = $eeTables !== [];
        $hasCommercialConfiguration = (bool) config('plugins.enabled') || (bool) config('features.saas');
        $summary = [
            'ee_schema_present' => $hasEeSchema,
            'ee_table_count' => count($eeTables),
            'commercial_configuration_present' => $hasCommercialConfiguration,
        ];

        if ((bool) $this->option('dry-run')) {
            $this->info($hasEeSchema ? 'EE schema detected.' : 'CE runtime detected.');
            $this->line(json_encode($summary, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $mode = $this->option('mode');
        if (! is_string($mode) || ! in_array($mode, [
            EditionRuntimeMode::COMMUNITY_MODE,
            EditionRuntimeMode::COMMERCIAL_MODE,
        ], true)) {
            $this->error('Choose an explicit runtime mode with --mode=ce or --mode=ee.');

            return self::FAILURE;
        }

        if (! (bool) $this->option('force')) {
            $this->error('Runtime migration requires --force after a backup and operator review.');

            return self::FAILURE;
        }

        $eeMetadata = $mode === EditionRuntimeMode::COMMERCIAL_MODE
            ? $this->eeMetadata()
            : null;
        $eeVersion = is_array($eeMetadata)
            ? $eeMetadata['ee_version'] ?? null
            : null;

        if ($mode === EditionRuntimeMode::COMMERCIAL_MODE && ! is_array($eeMetadata)) {
            $this->error('Cannot enable EE runtime mode without valid EE metadata.');

            return self::FAILURE;
        }

        if (is_array($eeMetadata)) {
            $reason = $releasePair->failureReason($eeMetadata, is_string($eeVersion) ? $eeVersion : null);
            if ($reason !== null) {
                $this->error("Cannot enable EE runtime mode: {$reason}");

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($mode, $eeVersion, $summary): void {
            EditionRuntimeMode::query()->updateOrCreate(
                ['singleton_key' => EditionRuntimeMode::SINGLETON_KEY],
                [
                    'mode' => $mode,
                    'migration_status' => EditionRuntimeMode::MIGRATION_APPLIED,
                    'contract_version' => EditionCompatibility::CONTRACT_VERSION,
                    'ee_version' => is_string($eeVersion) ? $eeVersion : null,
                    'detected_summary' => $summary,
                    'migration_summary' => [
                        'target_mode' => $mode,
                        'preserves_ce_data' => true,
                        'preserves_ee_tables' => true,
                        'preserves_hosted_billing_history' => true,
                    ],
                    'started_at' => now(),
                    'completed_at' => now(),
                ],
            );
        });

        $this->info("Edition runtime mode migrated to {$mode} without deleting edition data.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function eeTables(): array
    {
        return DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->where('table_name', 'like', 'ee%')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->filter(static fn (mixed $tableName): bool => is_string($tableName) && str_starts_with($tableName, 'ee_'))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function eeMetadata(): ?array
    {
        $manifestPath = base_path('plugins/gaeld-ee/plugin.json');
        if (! is_file($manifestPath)) {
            return null;
        }

        try {
            $contents = file_get_contents($manifestPath);
            if ($contents === false) {
                return null;
            }

            $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $metadata = $manifest['compatibility'] ?? null;

        return is_array($metadata) ? $metadata : null;
    }
}
