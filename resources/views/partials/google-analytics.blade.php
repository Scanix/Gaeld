{{-- Google SDKs are confined to the standalone, data-free conversion page. --}}
<script type="application/json" id="gaeld-google-config" nonce="{{ app('csp-nonce') }}">@json(['consentRevision' => config('services.google.consent_revision', 1)], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
