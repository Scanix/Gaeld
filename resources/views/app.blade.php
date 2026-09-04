<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title data-inertia>{{ config('app.name', 'Gaeld') }}</title>

        {{-- Favicon & icons --}}
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="msapplication-config" content="/browserconfig.xml">
        <meta name="theme-color" content="#33cc66">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Gäld">

        {{-- Prevent flash of wrong theme --}}
        <script nonce="{{ app('csp-nonce') }}">
          (function(){
            var t=localStorage.getItem('gaeld-theme')||'system';
            var d=t==='dark'||(t==='system'&&window.matchMedia('(prefers-color-scheme:dark)').matches);
            if(d)document.documentElement.classList.add('dark');
          })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @vite('resources/js/cookieConsent.js')
        @include('partials.google-analytics')
        @inertiaHead

        {{-- Remove service-worker registrations left by older releases. --}}
        <script nonce="{{ app('csp-nonce') }}">
          if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function (registrations) {
              registrations.forEach(function (registration) {
                registration.unregister();
              });
            });
          }
          if ('caches' in window) {
            caches.keys().then(function (keys) {
              return Promise.all(keys.filter(function (key) {
                return key.indexOf('gaeld-shell-') === 0;
              }).map(function (key) {
                return caches.delete(key);
              }));
            });
          }
        </script>
    </head>
    <body>
        @inertia
    </body>
</html>
