@if(config('services.google.gtm_id'))
{{-- 1. Default consent to "denied" before any tags fire --}}
<script nonce="{{ app('csp-nonce') }}">
  window.dataLayer = window.dataLayer || [];
  window.gtag = function(){window.dataLayer.push(arguments);};
  window.gaeldLoadGtm = function(){
    if (window.gaeldGtmLoaded) return;
    window.gaeldGtmLoaded = true;
    var f=document.getElementsByTagName('script')[0],
        j=document.createElement('script'),
        dl='dataLayer'!='dataLayer'?'&l=dataLayer':'';
    j.async=true;
    j.id='gaeld-gtm';
    j.src='https://www.googletagmanager.com/gtm.js?id={{ config('services.google.gtm_id') }}'+dl;
    f.parentNode.insertBefore(j,f);
  };
  window.gtag('consent', 'default', {
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
        window.gtag('consent', 'update', {
          'ad_storage': 'granted',
          'ad_user_data': 'granted',
          'ad_personalization': 'granted',
          'analytics_storage': 'granted'
        });
        window.gaeldLoadGtm();
      }
    }
  } catch(e) {}
</script>

@endif
