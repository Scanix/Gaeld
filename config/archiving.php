<?php

return [
    // The preservation profile is public and identical in CE and EE.
    'proofs_enabled' => env('ARCHIVE_PROOFS_ENABLED', false),
    'format_version' => '1.0.0',
    'proof_profile' => 'gaeld-sealed-package/1.0.0',
    'openssl' => env('ARCHIVE_OPENSSL_BINARY', 'openssl'),
    'process_timeout' => 120,
    'pdfa' => [
        'ghostscript' => env('ARCHIVE_GHOSTSCRIPT_BINARY', 'gs'),
        'verapdf' => env('ARCHIVE_VERAPDF_BINARY', '/opt/verapdf/verapdf'),
        'icc_profile' => env('ARCHIVE_ICC_PROFILE', '/usr/share/color/icc/ghostscript/srgb.icc'),
    ],
    'signing' => [
        'certificate' => env('ARCHIVE_SIGNING_CERTIFICATE'),
        'private_key' => env('ARCHIVE_SIGNING_PRIVATE_KEY'),
        'passphrase_file' => env('ARCHIVE_SIGNING_PASSPHRASE_FILE'),
        'chain' => env('ARCHIVE_SIGNING_CHAIN'),
        'trust_store' => env('ARCHIVE_SIGNING_TRUST_STORE'),
        'crl' => env('ARCHIVE_SIGNING_CRL'),
        'allowed_fingerprint' => env('ARCHIVE_SIGNING_FINGERPRINT'),
    ],
    'timestamp' => [
        'url' => env('ARCHIVE_TIMESTAMP_URL'),
        'bearer_token' => env('ARCHIVE_TIMESTAMP_TOKEN'),
        'bearer_token_file' => env('ARCHIVE_TIMESTAMP_TOKEN_FILE'),
        'basic_username_file' => env('ARCHIVE_TIMESTAMP_USERNAME_FILE'),
        'basic_password_file' => env('ARCHIVE_TIMESTAMP_PASSWORD_FILE'),
        'tls_ca_bundle' => env('ARCHIVE_TIMESTAMP_CA_BUNDLE'),
        'trust_store' => env('ARCHIVE_TIMESTAMP_TRUST_STORE'),
        'chain' => env('ARCHIVE_TIMESTAMP_CHAIN'),
        'crl' => env('ARCHIVE_TIMESTAMP_CRL'),
        'timeout' => 30,
        'renew_after_days' => 300,
    ],
    's3' => [
        'region' => env('ARCHIVE_S3_REGION', 'eu-central-1'),
        'endpoint' => env('ARCHIVE_S3_ENDPOINT'),
        'bucket' => env('ARCHIVE_S3_BUCKET'),
        'prefix' => env('ARCHIVE_S3_PREFIX', 'gaeld-preservation'),
        'key' => env('ARCHIVE_S3_ACCESS_KEY'),
        'secret' => env('ARCHIVE_S3_SECRET_KEY'),
        'tls_ca_bundle' => env('ARCHIVE_S3_CA_BUNDLE'),
        'use_path_style_endpoint' => env('ARCHIVE_S3_PATH_STYLE', false),
    ],
];
