<script type="application/json" id="gaeld-google-config" nonce="{{ app('csp-nonce') }}">@json(['config' => $googleAdsConfig, 'continueUrl' => $conversionContinueUrl, 'claimUrl' => $conversionClaimUrl], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
@vite('resources/js/googleAdsConversions.js')
