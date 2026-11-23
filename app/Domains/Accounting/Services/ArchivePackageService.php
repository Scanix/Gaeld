<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Contracts\ArchivePackageBuilder;
use App\Domains\Accounting\Models\ArchiveOutbox;
use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Proof\PdfArchivalConverter;
use App\Domains\Accounting\Proof\StandaloneArchiveVerifier;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ArchivePackageService implements ArchivePackageBuilder
{
    public function __construct(
        private readonly ArchiveSnapshotService $snapshots,
        private readonly ArchiveReportRenderer $reports,
        private readonly ArchiveAccessLog $audit,
    ) {}

    public function request(Organization $organization, FiscalYear $year, ?User $actor = null, string $provenance = 'native', bool $archivalPdf = false): ArchivePackage
    {
        if ($year->organization_id !== $organization->id || ! in_array($provenance, ['native', 'historical_backfill'], true)) {
            throw new \InvalidArgumentException('Invalid archive scope or provenance.');
        }

        return DB::transaction(function () use ($organization, $year, $actor, $provenance, $archivalPdf): ArchivePackage {
            Organization::withoutGlobalScopes()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $year = FiscalYear::withoutGlobalScopes()->where('organization_id', $organization->id)->whereKey($year->id)->lockForUpdate()->firstOrFail();
            $snapshot = $this->snapshots->capture($organization, $year);
            $snapshot['original_sources'] = $this->sources($snapshot, $organization->id);
            $snapshotHash = hash('sha256', self::json($snapshot));
            $latest = ArchivePackage::withoutGlobalScopes()->where('organization_id', $organization->id)
                ->where('fiscal_year_id', $year->id)->orderByDesc('version')->first();
            if ($latest && $latest->snapshot_sha256 === $snapshotHash && $latest->provenance === $provenance && $latest->archival_pdf === $archivalPdf) {
                return $latest;
            }
            $id = (string) Str::uuid();
            $package = ArchivePackage::create([
                'id' => $id, 'organization_id' => $organization->id, 'fiscal_year_id' => $year->id,
                'version' => ($latest->version ?? 0) + 1, 'provenance' => $provenance, 'archival_pdf' => $archivalPdf,
                'snapshot' => $snapshot, 'snapshot_sha256' => $snapshotHash,
                'package_path' => "archives/packages/{$organization->id}/{$id}", 'captured_at' => now(),
                'retention_until' => $year->end_date->copy()->endOfDay()->addYears(10),
            ]);
            foreach ($snapshot['original_sources'] as $source) {
                DB::table('archive_source_files')->insert([
                    'id' => (string) Str::uuid(), 'archive_package_id' => $id, 'organization_id' => $organization->id,
                    'source_type' => $source['type'], 'source_id' => $source['id'], 'source_path' => $source['source_path'],
                    'source_sha256' => $source['sha256'], 'size' => $source['size'],
                ]);
            }
            ArchiveOutbox::create(['archive_package_id' => $id, 'available_at' => now()]);
            $this->audit->record($organization->id, $id, 'package.requested', $actor, ['snapshot_sha256' => $snapshotHash, 'provenance' => $provenance]);

            return $package;
        });
    }

    public function prepare(ArchivePackage $package, bool $archivalPdf = false): ArchivePackage
    {
        if ($package->archival_pdf !== $archivalPdf) {
            throw new \LogicException('The report profile is fixed at capture. Request a new version to change it.');
        }

        $lock = 'archive-package:'.$package->id;
        $acquired = DB::selectOne('SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS acquired', [$lock]);
        if (! $acquired->acquired) {
            throw new \RuntimeException('This package is already being prepared.');
        }
        try {
            $package->refresh();
            if ($package->manifest_sha256 !== null) {
                $result = $this->verify($package);
                if (! $result['valid']) {
                    throw new \RuntimeException(implode('; ', $result['issues']));
                }

                return $package;
            }
            $package->update(['state' => 'preparing', 'failure' => null]);
            $snapshot = $package->snapshot;
            if (! hash_equals($package->snapshot_sha256, hash('sha256', self::json($snapshot)))) {
                throw new \RuntimeException('Frozen accounting snapshot has changed.');
            }
            $files = [];
            $disk = Storage::disk('local');
            $minimumRetention = Carbon::parse($snapshot['fiscal_year'][0]['end_date'])->endOfDay()->addYears(10)->toIso8601String();
            $this->write($package, 'README.txt', $this->readme($package), 'documentation', $files);
            $this->write($package, 'retention-policy.json', self::json([
                'schema_version' => '1.0.0',
                'policy_profile' => 'gaeld-conservation/1.0.0',
                'package_id' => $package->id,
                'organization_id' => $package->organization_id,
                'minimum_retention_until' => $minimumRetention,
                'minimum_retention' => '10 years after the fiscal-year end',
                'automatic_disposal' => false,
                'subsequent_extensions_and_holds' => 'Consult protected-storage receipts and subsequent conservation control records; this frozen policy is never rewritten.',
                'content_profile' => $archivalPdf ? 'PDF/A-2b with package signature, timestamp and protected-storage evidence required' : 'Content integrity only; no archival proof profile is asserted',
                'historical_integrity_asserted' => false,
                'responsible_roles' => [
                    'organization_administrator' => 'Assign named access rights, retain the written operating procedure, authorize retention changes and appoint a successor.',
                    'installation_operator' => 'Monitor daily controls, investigate failures, protect recovery keys, maintain separate encrypted backups and renew proof evidence before expiry.',
                    'accounting_reviewer' => 'Review accounting completeness and annual statements; preserve corrections as subsequent versions.',
                ],
                'controls' => ['daily_integrity_and_inventory_check', 'annual_completeness_check_and_documented_isolated_restoration'],
                'control_and_access_records' => 'data/snapshot.json#archive_access_events',
                'control_actions' => ['control.daily', 'control.annual'],
                'backup_scope' => 'Database, original documents, packages and evidence, application key, configuration and required recovery secrets.',
                'independent_trust' => 'Obtain signer and TSA trust anchors, authorized signer fingerprints and the expected manifest hash from a trusted source outside this package.',
            ]), 'policy', $files);
            $this->write($package, 'data/snapshot.json', self::json($snapshot), 'snapshot', $files);
            $this->write($package, 'data/statements.json', self::json($this->reports->statements($snapshot)), 'data', $files);
            foreach ($snapshot as $name => $rows) {
                if (is_array($rows) && array_is_list($rows) && $rows !== []) {
                    $this->write($package, 'data/'.$name.'.csv', $this->csv($rows), 'data', $files);
                }
            }
            foreach ($snapshot['original_sources'] as $source) {
                $disk = Storage::disk('local');
                if ($source['sha256'] === null || ! $disk->exists($source['source_path'])) {
                    throw new \RuntimeException('Required original document is missing: '.$source['type'].'/'.$source['id']);
                }
                $bytes = $disk->get($source['source_path']);
                if (! hash_equals($source['sha256'], hash('sha256', $bytes)) || strlen($bytes) !== $source['size']) {
                    throw new \RuntimeException('Original document changed since capture: '.$source['type'].'/'.$source['id']);
                }
                $this->write($package, $source['package_path'], $bytes, 'original', $files);
            }
            foreach ($this->reports->render($snapshot) as $filename => $bytes) {
                $target = 'reports/'.$filename;
                $disk = Storage::disk('local');
                $absolute = $disk->path($package->package_path.'/'.$target);
                if ($archivalPdf) {
                    $input = $disk->path($package->package_path.'/working/'.$filename);
                    $disk->makeDirectory($package->package_path.'/working');
                    $disk->makeDirectory($package->package_path.'/reports');
                    if (! is_file($input)) {
                        file_put_contents($input, $bytes, LOCK_EX);
                    }
                    $converter = app(PdfArchivalConverter::class);
                    $validationPath = 'validation/'.$filename.'.xml';
                    if (is_file($absolute)) {
                        $validation = $converter->validate($absolute);
                        if ($disk->exists($package->package_path.'/'.$validationPath)) {
                            $validation = $disk->get($package->package_path.'/'.$validationPath);
                        }
                    } else {
                        $validation = $converter->convert($input, $absolute)['validation_report'];
                    }
                    $this->write($package, $validationPath, $validation, 'validation', $files);
                    $bytes = file_get_contents($absolute);
                    if ($bytes === false) {
                        throw new \RuntimeException('Missing converted PDF.');
                    }
                } elseif (is_file($absolute)) {
                    // PDF metadata is nondeterministic; a resumed attempt keeps its first generated bytes.
                    $bytes = file_get_contents($absolute);
                }
                if ($bytes === false) {
                    throw new \RuntimeException('Unable to read the generated report.');
                }
                $this->write($package, $target, $bytes, 'report', $files);
            }
            usort($files, fn (array $left, array $right) => $left['path'] <=> $right['path']);
            $workingDirectory = $package->package_path.'/working';
            if ($disk->directoryExists($workingDirectory) && ! $disk->deleteDirectory($workingDirectory)) {
                throw new \RuntimeException('Cannot remove intermediate report files before preparing the package.');
            }
            $year = $snapshot['fiscal_year'][0];
            $manifest = [
                'schema_version' => '1.0.0', 'proof_profile' => $archivalPdf ? 'gaeld-sealed-package/1.0.0' : 'gaeld-content-package/1.0.0',
                'package_id' => $package->id, 'organization_id' => $package->organization_id,
                'fiscal_year' => ['id' => $package->fiscal_year_id, 'start_date' => substr($year['start_date'], 0, 10), 'end_date' => substr($year['end_date'], 0, 10)],
                'version' => $package->version, 'provenance' => $package->provenance,
                'captured_at' => $package->captured_at->toIso8601String(),
                'retention_until' => $minimumRetention,
                'snapshot_sha256' => $package->snapshot_sha256,
                'report_profile' => $archivalPdf ? 'PDF/A-2b' : 'PDF',
                'historical_integrity_asserted' => false,
                'files' => $files,
            ];
            $manifestBytes = self::json($manifest);
            $unused = [];
            $this->write($package, 'manifest.json', $manifestBytes, 'manifest', $unused);
            $package->update(['manifest_sha256' => hash('sha256', $manifestBytes), 'state' => 'prepared', 'prepared_at' => now(), 'failure' => null]);
            $this->audit->record($package->organization_id, $package->id, 'package.prepared', metadata: ['manifest_sha256' => $package->manifest_sha256]);

            return $package;
        } finally {
            DB::select('SELECT pg_advisory_unlock(hashtextextended(?, 0))', [$lock]);
        }
    }

    /** @return array{valid: bool, issues: list<string>} */
    public function verify(ArchivePackage $package): array
    {
        $issues = [];
        try {
            $disk = Storage::disk('local');
            $manifestPath = $package->package_path.'/manifest.json';
            if (! $package->manifest_sha256 || ! $disk->exists($manifestPath)) {
                throw new \RuntimeException('Package preparation is incomplete.');
            }
            $bytes = $disk->get($manifestPath);
            if (! hash_equals($package->manifest_sha256, hash('sha256', $bytes))) {
                throw new \RuntimeException('Manifest integrity check failed.');
            }
            $manifest = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['package_id'] ?? null) !== $package->id || ($manifest['organization_id'] ?? null) !== $package->organization_id || ($manifest['snapshot_sha256'] ?? null) !== $package->snapshot_sha256) {
                throw new \RuntimeException('Manifest identity check failed.');
            }
            if (($manifest['proof_profile'] ?? null) !== ($package->archival_pdf ? 'gaeld-sealed-package/1.0.0' : 'gaeld-content-package/1.0.0')
                || ($manifest['report_profile'] ?? null) !== ($package->archival_pdf ? 'PDF/A-2b' : 'PDF')) {
                throw new \RuntimeException('The manifest report profile differs from the immutable package profile.');
            }
            (new StandaloneArchiveVerifier)->verifier()->inspect($disk->path($package->package_path), $package->manifest_sha256);
            $expectedPaths = [$package->package_path.'/manifest.json'];
            $root = realpath($disk->path($package->package_path));
            foreach ($manifest['files'] as $file) {
                $this->assertSafePath($file['path']);
                $path = $package->package_path.'/'.$file['path'];
                if (in_array($path, $expectedPaths, true)) {
                    throw new \RuntimeException('Duplicate manifest path.');
                }
                $expectedPaths[] = $path;
                $actualPath = realpath($disk->path($path));
                if (! $root || ! $actualPath || ! str_starts_with($actualPath, $root.DIRECTORY_SEPARATOR) || is_link($disk->path($path))
                    || ! $disk->exists($path) || $disk->size($path) !== $file['size'] || ! hash_equals($file['sha256'], hash('sha256', $disk->get($path)))) {
                    $issues[] = 'Missing or changed package file: '.$file['path'];
                }
            }
            $actualPaths = array_values(array_filter($disk->allFiles($package->package_path), fn (string $path): bool => ! $package->archival_pdf || ! str_starts_with($path, $package->package_path.'/evidence/')));
            sort($expectedPaths);
            sort($actualPaths);
            if ($expectedPaths !== $actualPaths) {
                $issues[] = 'Package inventory contains missing or unexpected files.';
            }
        } catch (\Throwable $exception) {
            $issues[] = $exception->getMessage();
        }
        if ($issues === []) {
            $package->update(['verified_at' => now()]);
        } elseif ($package->manifest_sha256 !== null) {
            $package->update(['state' => 'incident', 'failure' => implode('; ', $issues)]);
        }

        return ['valid' => $issues === [], 'issues' => $issues];
    }

    public function export(ArchivePackage $package): string
    {
        $result = $this->verify($package);
        if (! $result['valid']) {
            throw new \RuntimeException(implode('; ', $result['issues']));
        }
        $disk = Storage::disk('local');
        $manifest = json_decode($disk->get($package->package_path.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $disk->makeDirectory('archives/exports');
        $target = $disk->path('archives/exports/'.Str::uuid().'.zip');
        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new \RuntimeException('Cannot create archive export.');
        }
        try {
            foreach (array_merge(['manifest.json'], array_column($manifest['files'], 'path')) as $path) {
                if (! $zip->addFile($disk->path($package->package_path.'/'.$path), $path)) {
                    throw new \RuntimeException('Cannot add archive export file.');
                }
            }
            foreach ($disk->allFiles($package->package_path.'/evidence') as $path) {
                if (is_link($disk->path($path)) || ! $zip->addFile($disk->path($path), substr($path, strlen($package->package_path) + 1))) {
                    throw new \RuntimeException('Cannot add proof evidence to export.');
                }
            }
            if (! $zip->close()) {
                throw new \RuntimeException('Cannot finish archive export.');
            }
        } catch (\Throwable $exception) {
            @$zip->close();
            @unlink($target);
            throw $exception;
        }

        return $target;
    }

    public function retry(ArchivePackage $package): ArchivePackage
    {
        return DB::transaction(function () use ($package): ArchivePackage {
            $package = ArchivePackage::withoutGlobalScopes()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            $package->update(['state' => $package->manifest_sha256 === null ? 'pending' : 'prepared', 'failure' => null]);
            ArchiveOutbox::query()->where('archive_package_id', $package->id)->update(['available_at' => now(), 'dispatched_at' => null, 'completed_at' => null, 'attempts' => 0]);

            return $package;
        });
    }

    private function readme(ArchivePackage $package): string
    {
        $profile = $package->archival_pdf ? 'PDF/A-2b; a complete signature, timestamp and protected-storage receipt are required.' : 'Ordinary PDF; content integrity only, without an archival proof claim.';
        $capturedAt = $package->captured_at->toIso8601String();

        return <<<TEXT
Gäld conservation package — instructions version 1.0.0

Package: {$package->id}
Organization: {$package->organization_id}
Fiscal year: {$package->fiscal_year_id}
Version: {$package->version}
Captured: {$capturedAt}
Provenance: {$package->provenance}
Report profile: {$profile}

READING THE PACKAGE
manifest.json identifies this version and lists every preserved content file with its byte size and SHA-256 digest.
data/snapshot.json is the frozen accounting dataset. Decimal amounts are strings; preserve exact decimal arithmetic when reusing them.
data/statements.json contains readable statement values; reports/ contains the journal, balance sheet and income statement PDFs.
originals/ preserves source-document bytes. A generated report is not a replacement for a missing original document.
data/archive_access_events.csv and the archive_access_events array in data/snapshot.json contain access records and control.daily/control.annual reports known at capture. Empty datasets have no CSV. journal_events records accounting changes.
retention-policy.json contains the versioned retention policy and operating responsibilities. Later checks, extensions or legal holds are recorded separately and do not rewrite this package.
evidence/ is reserved for append-only signature, timestamp, storage and renewal evidence. These files are outside the initial manifest to avoid circular references; their separate proof chain must also be verified.

VERIFYING INDEPENDENTLY
Use a trusted copy of Gäld scripts/archive-verifier/verify.php and Verifier.php with PHP 8.4+, OpenSSL and, for the archival profile, veraPDF. Obtain these tools independently of this package.
Obtain trusted signer and timestamp-authority CA bundles, authorized signer certificate SHA-256 fingerprints, and the expected manifest SHA-256 from an independently protected catalog. Certificates contained in this package do not establish trust on their own.
For the archival profile, run:
php verify.php --directory=/path/to/package --signer-trust=/trusted/signer-ca.pem --tsa-trust=/trusted/tsa-ca.pem --signer-fingerprint=AUTHORIZED_SHA256 --expected-manifest-sha256=INDEPENDENT_MANIFEST_SHA256 --verapdf=/path/to/verapdf
For content-only inspection, run:
php verify.php --directory=/path/to/package --integrity-only --expected-manifest-sha256=INDEPENDENT_MANIFEST_SHA256
The manifest hash does not identify the latest renewal evidence. Offline verification reports latest_evidence_pinned=false; compare the complete evidence inventory with independently retained protected-storage catalog versions to detect an older proof set.
Content-only success does not establish signature validity, trusted time, protected storage or full conservation compliance. Independently check the protected-storage inventory and retention/hold receipts. Preserve verification results and investigate failures without replacing evidence.

OPERATING AND RECOVERING
The organization appoints an administrator, installation operator and accounting reviewer, and keeps their names and access rights in its controlled operating procedure. Grant only the necessary company access and review it when responsibilities change.
The operator monitors daily checks and failures, maintains a separate encrypted recovery backup, and keeps recovery keys separately accessible to authorized successors. A working backup is distinct from an immutable accounting archive.
At least annually, restore a complete backup in an isolated environment, verify content and proof chains, reconcile the package inventory and supporting documents, and retain a dated report identifying the operator, restored environment, package hashes and outcome.
Restore the database, original documents, package/evidence files, original application key, configuration and required secrets together. Do not overwrite this package to repair accounting data; preserve corrections in a new version.
Retain the package for at least the frozen policy period and honor any later extension or legal hold. No automatic disposal is authorized by these instructions. Renew cryptographic evidence before its validity or algorithms become insufficient, without replacing the original manifest.

LIMITS
These instructions describe the package format and required operating steps; they do not certify the organization's legal compliance. Historical imports assert integrity only from capture onward. Missing or failed proof stages remain incomplete even when the accounting PDFs can be read.

TEXT;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array<string, mixed>>
     */
    private function sources(array $snapshot, string $organizationId): array
    {
        $sources = [];
        foreach (['expenses' => ['receipt_path', 'receipt', "receipts/{$organizationId}/"], 'invoices' => ['justificatif_path', 'justificatif', "justificatifs/{$organizationId}/"], 'legal_archives' => ['storage_path', 'legacy_archive', "archives/{$organizationId}/"], 'issued_document_files' => ['storage_path', 'issued_document', "archives/issued/{$organizationId}/"]] as $dataset => [$column, $type, $prefix]) {
            foreach ($snapshot[$dataset] ?? [] as $row) {
                if (empty($row[$column])) {
                    continue;
                }
                $path = $row[$column];
                $this->assertSafePath($path);
                if (! str_starts_with($path, $prefix)) {
                    throw new \RuntimeException('Original document path is outside the organization.');
                }
                $disk = Storage::disk('local');
                $bytes = $disk->exists($path) ? $disk->get($path) : null;
                $hash = $bytes === null ? null : hash('sha256', $bytes);
                if (isset($row['checksum_sha256']) && $hash !== null && ! hash_equals($row['checksum_sha256'], $hash)) {
                    throw new \RuntimeException('Original evidence checksum is invalid.');
                }
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $extension = preg_match('/^[a-z0-9]{1,10}$/D', $extension) ? '.'.$extension : '.bin';
                $sources[] = ['type' => $type, 'id' => (string) $row['id'], 'source_path' => $path, 'package_path' => 'originals/'.$type.'-'.$row['id'].$extension, 'sha256' => $hash, 'size' => $bytes === null ? null : strlen($bytes)];
            }
        }

        return $sources;
    }

    private function assertSafePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, "\0") || in_array('..', explode('/', $path), true)) {
            throw new \RuntimeException('Unsafe archive path.');
        }
    }

    /** @param list<array<string, mixed>> $files */
    private function write(ArchivePackage $package, string $path, string $bytes, string $role, array &$files): void
    {
        $this->assertSafePath($path);
        $disk = Storage::disk('local');
        $target = $package->package_path.'/'.$path;
        if ($disk->exists($target)) {
            if (! hash_equals(hash('sha256', $bytes), hash('sha256', $disk->get($target)))) {
                throw new \RuntimeException('An existing package artifact cannot be replaced: '.$path);
            }
        } elseif (! $disk->put($target, $bytes)) {
            throw new \RuntimeException('Cannot write archive artifact: '.$path);
        }
        $files[] = ['path' => $path, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'role' => $role];
    }

    /** @param list<array<string, mixed>> $rows */
    private function csv(array $rows): string
    {
        $columns = array_keys($rows[0]);
        sort($columns);
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Cannot create the CSV stream.');
        }
        fputcsv($stream, $columns, escape: '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $key) => is_array($row[$key] ?? null) ? self::json($row[$key]) : ($row[$key] ?? ''), $columns), escape: '');
        }
        rewind($stream);
        $bytes = stream_get_contents($stream);
        fclose($stream);

        return $bytes;
    }

    public static function json(mixed $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (! is_array($item)) {
                return $item;
            }
            if (! array_is_list($item)) {
                ksort($item);
            }

            return array_map($normalize, $item);
        };

        return json_encode($normalize($value), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }
}
