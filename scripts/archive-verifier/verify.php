#!/usr/bin/env php
<?php

require_once __DIR__.'/Verifier.php';

use Gaeld\ArchiveVerifier\Verifier;

$options = getopt('', ['directory:', 'signer-trust:', 'tsa-trust:', 'signer-fingerprint:', 'expected-manifest-sha256:', 'openssl:', 'verapdf:', 'integrity-only']);
try {
    if (empty($options['directory']) || ! is_string($options['directory'])) {
        throw new RuntimeException('Usage: php verify.php --directory=/archive --signer-trust=/external/ca.pem --tsa-trust=/external/tsa-ca.pem --signer-fingerprint=<sha256> [--expected-manifest-sha256=<sha256>] [--verapdf=/path/verapdf]. --integrity-only checks content without asserting the preservation profile.');
    }
    $settings = [];
    foreach ($options as $key => $value) {
        if ($key === 'integrity-only') {
            continue;
        }
        if (! is_string($value)) {
            throw new RuntimeException('Each verifier option must be supplied exactly once with a string value.');
        }
        $settings[str_replace('-', '_', $key)] = $value;
    }
    $verifier = new Verifier($settings);
    $expected = $settings['expected_manifest_sha256'] ?? null;
    if (array_key_exists('integrity-only', $options)) {
        $manifest = $verifier->inspect($options['directory'], $expected);
        $result = ['content_integrity' => true, 'proof_profile_verified' => false, 'package_id' => $manifest['package_id'], 'inventory_pinned' => $expected !== null];
    } else {
        $result = $verifier->verify($options['directory'], $expected);
    }
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['valid' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
}
