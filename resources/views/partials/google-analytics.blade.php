@if(config('services.google.gtm_id'))
{{-- 1. Default consent to "denied" before any tags fire --}}
<script nonce="{{ app('csp-nonce') }}">
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('consent', 'default', {
    'ad_storage': 'denied',
    'ad_user_data': 'denied',
    'ad_personalization': 'denied',
    'analytics_storage': 'denied',
    'wait_for_update': 500
  });

  // 2. Restore consent for returning visitors
  try {
    var cc = document.cookie.match('(?:^|;)\\s*gaeld_cookie_consent=([^;]*)');
    if (cc && cc[1]) {
      var parsed = JSON.parse(decodeURIComponent(cc[1]));
      if (parsed && parsed.categories && parsed.categories.indexOf('analytics') > -1) {
        gtag('consent', 'update', {
          'ad_storage': 'granted',
          'ad_user_data': 'granted',
          'ad_personalization': 'granted',
          'analytics_storage': 'granted'
        });
      }
    }
  } catch(e) {}
</script>

{{-- 3. Load GTM container --}}
<script nonce="{{ app('csp-nonce') }}">
  (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
  new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
  j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
  'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
  })(window,document,'script','dataLayer','{{ config('services.google.gtm_id') }}');
</script>
@endif
