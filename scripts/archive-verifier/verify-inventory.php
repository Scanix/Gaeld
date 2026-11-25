#!/usr/bin/env php
<?php

require_once __DIR__.'/Verifier.php';

use Gaeld\ArchiveVerifier\Verifier;

/** @param array<string, mixed> $object */
function recoveredFile(Verifier $paths, string $root, array $object): string
{
    if (! is_string($object['version_id'] ?? null) || in_array($object['version_id'], ['', 'null'], true)
        || ! is_string($object['sha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $object['sha256'])
        || ! is_int($object['size'] ?? null) || $object['size'] < 0 || ! is_string($object['key'] ?? null)) {
        throw new RuntimeException('Invalid catalog object reference.');
    }
    $path = $paths->path($root, $object['key']);
    if (filesize($path) !== $object['size'] || hash_file('sha256', $path) !== $object['sha256']) {
        throw new RuntimeException('Recovered object differs from the checkpoint: '.$object['key']);
    }

    return $path;
}

try {
    $options = getopt('', ['directory:', 'checkpoint:', 'expected-checkpoint-sha256:']);
    foreach (['directory', 'checkpoint', 'expected-checkpoint-sha256'] as $option) {
        if (! is_string($options[$option] ?? null) || $options[$option] === '') {
            throw new RuntimeException('Supply --directory, --checkpoint and --expected-checkpoint-sha256.');
        }
    }
    $bytes = file_get_contents($options['checkpoint']);
    if ($bytes === false || ! hash_equals($options['expected-checkpoint-sha256'], hash('sha256', $bytes))) {
        throw new RuntimeException('The checkpoint differs from the independently retained digest.');
    }
    $snapshot = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    if (($snapshot['schema'] ?? null) !== 'gaeld-recovery-checkpoint/1' || ! is_string($snapshot['prefix'] ?? null)
        || ! is_string($snapshot['bucket'] ?? null) || $snapshot['bucket'] === '' || ! is_string($snapshot['observed_at'] ?? null)
        || ! is_array($snapshot['catalogs'] ?? null) || ! array_is_list($snapshot['catalogs']) || $snapshot['catalogs'] === []) {
        throw new RuntimeException('Expected a populated recovery checkpoint.');
    }
    $paths = new Verifier;
    $packages = $organizations = $catalogs = $verified = [];
    foreach ($snapshot['catalogs'] as $catalog) {
        $path = recoveredFile($paths, $options['directory'], $catalog);
        $payload = file_get_contents($path);
        if ($payload === false || hash('sha256', $payload) !== $catalog['sha256']) {
            throw new RuntimeException('Recovered catalog changed during verification.');
        }
        $receipt = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $org = $receipt['organization_id'] ?? '';
        $package = $receipt['package_id'] ?? '';
        $manifest = $receipt['manifest_sha256'] ?? '';
        $prefix = $snapshot['prefix'].'/';
        if (($receipt['schema_version'] ?? null) !== '1.0.0' || ($receipt['bucket'] ?? null) !== $snapshot['bucket']
            || ! preg_match('/^[a-zA-Z0-9_-]+$/D', $org) || ! preg_match('/^[a-zA-Z0-9_-]+$/D', $package)
            || ! preg_match('/^[a-f0-9]{64}$/D', $manifest)
            || $catalog['key'] !== $prefix.'catalog/'.$org.'/'.$package.'/'.$catalog['sha256'].'.json'
            || ! is_array($receipt['objects'] ?? null) || ! array_is_list($receipt['objects'])) {
            throw new RuntimeException('Recovered catalog identity or object list is invalid.');
        }
        $identity = $org.'/'.$package;
        $catalogId = $catalog['key']."\0".$catalog['version_id'];
        if (isset($catalogs[$catalogId]) || (isset($packages[$identity]) && $packages[$identity] !== $manifest)) {
            throw new RuntimeException('Duplicate catalog version or conflicting package manifest.');
        }
        $catalogs[$catalogId] = true;
        $organizations[$org] = true;
        $packages[$identity] = $manifest;
        $entries = [];
        foreach ($receipt['objects'] as $object) {
            $relative = $object['path'] ?? null;
            if (! is_string($relative) || isset($entries[$relative]) || ($object['key'] ?? null) !== $prefix.$identity.'/'.$manifest.'/'.$relative) {
                throw new RuntimeException('An object is duplicated or belongs to another package.');
            }
            $entries[$relative] = $object['sha256'];
            // Immutable keys recur in later catalogs. Verify bytes once for each exact reference.
            $reference = json_encode([$object['key'], $object['version_id'], $object['size'], $object['sha256']], JSON_THROW_ON_ERROR);
            if (! isset($verified[$reference])) {
                recoveredFile($paths, $options['directory'], $object);
                $verified[$reference] = true;
            }
        }
        if (($entries['manifest.json'] ?? null) !== $manifest) {
            throw new RuntimeException('The recovered catalog lacks its package manifest.');
        }
    }
    echo json_encode([
        'valid' => true, 'checkpoint_sha256' => $options['expected-checkpoint-sha256'], 'as_of' => $snapshot['observed_at'],
        'organizations' => count($organizations), 'packages' => count($packages), 'catalog_versions' => count($catalogs),
        'object_versions' => count($verified), 'live_storage_protection_checked' => false,
    ], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['valid' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
}
