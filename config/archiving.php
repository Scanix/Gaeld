<?php

return [
    // R2 prepares ordinary-PDF content packages in both CE and EE.
    'format_version' => '1.0.0',
    'proof_profile' => 'gaeld-content-package/1.0.0',
    // An unsupported proof request is rejected, never silently downgraded.
    'proofs_enabled' => env('ARCHIVE_PROOFS_ENABLED', false),
];
